<?php

namespace Tests\Feature;

use App\Models\ExcelImportBatch;
use App\Models\TourGroup;
use App\Models\User;
use App\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ExcelImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_confirmation_imports_ready_history_rows_into_dashboard(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => Role::Admin]);
        $manager = User::factory()->create(['role' => Role::Manager]);
        $file = $this->workbookUpload();

        $this->getJson('/api/admin/imports')->assertUnauthorized();
        $this->actingAs($manager)->getJson('/api/admin/imports')->assertForbidden();

        $response = $this->actingAs($admin)->postJson('/api/admin/imports/upload', ['file' => $file])->assertCreated();
        $batchId = $response->json('batch.id');
        $duplicateUpload = UploadedFile::fake()->createWithContent('history-copy.xlsm', file_get_contents($file->getRealPath()));
        $this->actingAs($admin)->postJson('/api/admin/imports/upload', ['file' => $duplicateUpload])
            ->assertCreated()->assertJsonPath('batch.id', $batchId);
        $this->assertSame(1, ExcelImportBatch::query()->count());
        $batch = ExcelImportBatch::query()->findOrFail($batchId);

        $analyzed = $this->actingAs($admin)->postJson("/api/admin/imports/{$batchId}/analyze");
        $analyzed->assertOk()->assertJsonPath('batch.summary.total_rows', 1);
        $this->assertSame(1, $analyzed->json('batch.summary.ready_rows'), json_encode($batch->refresh()->rows()->first()->issues, JSON_UNESCAPED_UNICODE));

        $row = $batch->rows()->firstOrFail();
        $this->assertSame('0901234567', $row->mapped_values['guide_phone']);
        $this->assertSame('2026-11-10', $row->mapped_values['tour_date']);
        $this->assertSame(18, $row->mapped_values['paid_tickets']);

        $this->actingAs($admin)->postJson("/api/admin/imports/{$batchId}/confirm")
            ->assertOk()->assertJsonPath('result.imported', 1)->assertJsonPath('result.skipped', 0);

        $tour = TourGroup::query()->firstOrFail();
        $this->assertSame('APPROVED', $tour->status);
        $this->assertTrue($tour->is_reconciled);
        $this->assertTrue($tour->counts_in_dashboard);
        $this->assertSame('REAL_IMPORTED', $tour->source_type);
        $this->assertSame(540000, $tour->ticket_cost);
        $this->assertSame(400000, $tour->boat_cost);
        $this->assertSame(50000, $tour->services()->where('service_type', 'coconut')->value('commission_amount'));
        $this->assertDatabaseHas('tour_photos', ['tour_group_id' => $tour->id, 'photo_type' => 'large_frame', 'unit_price_snapshot' => 180000, 'unit_cost_snapshot' => 75000]);
        $this->assertDatabaseHas('tour_photos', ['tour_group_id' => $tour->id, 'photo_type' => 'large_plastic', 'unit_price_snapshot' => 100000, 'unit_cost_snapshot' => 10000, 'commission' => 10000]);

        $this->actingAs($manager)->getJson('/api/dashboard/summary?mode=day&date=2026-11-10')
            ->assertOk()->assertJsonPath('summary.tour_count', 1)->assertJsonPath('summary.ticket_revenue', 1000000)
            ->assertJsonPath('pending_records.tours_pending_reconciliation', 0);

        $this->actingAs($admin)->postJson("/api/admin/imports/{$batchId}/confirm")
            ->assertStatus(422);
        $this->assertSame(1, TourGroup::query()->count());

        $this->actingAs($admin)->postJson("/api/admin/imports/{$batchId}/include-in-dashboard")
            ->assertOk()->assertJsonPath('result.included_tours', 0);
        $this->assertDatabaseHas('tour_groups', ['id' => $tour->id, 'is_reconciled' => true, 'counts_in_dashboard' => true]);
        $this->assertSame('APPROVED', $tour->refresh()->status);
        $this->actingAs($manager)->getJson('/api/dashboard/summary?mode=day&date=2026-11-10')
            ->assertOk()->assertJsonPath('summary.tour_count', 1)->assertJsonPath('summary.ticket_revenue', 1000000)->assertJsonPath('summary.photo_revenue', 280000);
    }

    public function test_reference_workbook_keeps_the_stale_photo_total_out_of_ready_rows(): void
    {
        $path = base_path('../reference/CAO THỊ_QUẢN LÝ THU CHI_FINAL (2).xlsm');
        if (! is_file($path)) {
            $this->markTestSkipped('Reference workbook is not installed in this checkout.');
        }

        Storage::fake('local');
        $admin = User::factory()->create(['role' => Role::Admin]);
        $file = new UploadedFile($path, 'CAO THỊ_QUẢN LÝ THU CHI_FINAL.xlsm', 'application/octet-stream', UPLOAD_ERR_OK, true);
        $batchId = $this->actingAs($admin)->postJson('/api/admin/imports/upload', ['file' => $file])->assertCreated()->json('batch.id');

        $analyzed = $this->actingAs($admin)->postJson("/api/admin/imports/{$batchId}/analyze");
        $analyzed->assertOk()->assertJsonPath('batch.summary.total_rows', 12);
        $rows = ExcelImportBatch::query()->findOrFail($batchId)->rows()->get(['source_row', 'status', 'issues']);
        $this->assertSame(9, $analyzed->json('batch.summary.ready_rows'), json_encode($rows->toArray(), JSON_UNESCAPED_UNICODE));
        $this->assertSame(3, $analyzed->json('batch.summary.rows_needing_review'));
        $this->assertSame(4, $analyzed->json('batch.summary.archived_legacy_expense_rows'));
        $this->assertSame(19030000, $analyzed->json('batch.summary.source_totals.total_revenue'));
        $this->assertSame(10390000, $analyzed->json('batch.summary.ready_totals.total_revenue'));

        $row = ExcelImportBatch::query()->findOrFail($batchId)->rows()->where('source_row', 19)->firstOrFail();
        $this->assertSame('needs_review', $row->status);
        $this->assertStringContainsString('photo_revenue', implode(' ', $row->issues));
        $this->assertSame(4260000, $row->source_values['AE']['value']);
    }

    public function test_import_does_not_overwrite_an_existing_date_and_tour_code(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => Role::Admin]);
        $existing = TourGroup::query()->create([
            'tour_date' => '2026-11-10', 'sequence' => 1, 'code' => 'CT-01-10/11',
            'created_by' => $admin->id, 'ticket_revenue' => 123456, 'status' => 'APPROVED',
        ]);
        $response = $this->actingAs($admin)->postJson('/api/admin/imports/upload', ['file' => $this->workbookUpload()])->assertCreated();
        $batchId = $response->json('batch.id');

        $this->actingAs($admin)->postJson("/api/admin/imports/{$batchId}/analyze")
            ->assertOk()->assertJsonPath('batch.summary.ready_rows', 0)->assertJsonPath('batch.summary.rows_needing_review', 1);
        $this->actingAs($admin)->postJson("/api/admin/imports/{$batchId}/confirm")->assertStatus(422);

        $this->assertSame(123456, $existing->refresh()->ticket_revenue);
        $this->assertSame(1, TourGroup::query()->count());
    }

    public function test_upload_rejects_invalid_corrupt_and_extension_spoofed_workbooks(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => Role::Admin]);

        foreach ([
            UploadedFile::fake()->createWithContent('notes.xlsm', 'not an OOXML workbook'),
            UploadedFile::fake()->createWithContent('broken.xlsm', "PK\x03\x04broken archive"),
            $this->workbookUpload(false),
        ] as $file) {
            $this->actingAs($admin)->postJson('/api/admin/imports/upload', ['file' => $file])->assertUnprocessable();
        }

        $this->assertSame(0, ExcelImportBatch::query()->count());
        $this->assertSame(0, TourGroup::query()->count());
    }

    public function test_import_endpoints_report_missing_schema_as_service_unavailable(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => Role::Admin]);
        Schema::drop('excel_import_batches');

        $this->actingAs($admin)->getJson('/api/admin/imports')
            ->assertStatus(503)
            ->assertJsonPath('code', 'IMPORT_SCHEMA_NOT_READY');

        $this->actingAs($admin)->postJson('/api/admin/imports/upload', [
            'file' => UploadedFile::fake()->createWithContent('workbook.xlsm', 'not used until schema exists'),
        ])->assertStatus(503)->assertJsonPath('code', 'IMPORT_SCHEMA_NOT_READY');
    }

    private function workbookUpload(bool $macroEnabled = true): UploadedFile
    {
        $spreadsheet = new Spreadsheet;
        $journal = $spreadsheet->getActiveSheet();
        $journal->setTitle('NHAT KY DOAN');
        foreach (['A4' => 'Ngày', 'B4' => 'Mã đoàn', 'C4' => 'Tên HDV', 'D4' => 'SĐT HDV', 'E4' => 'Khách Việt', 'F4' => 'Khách quốc tế', 'I4' => 'THU TIỀN VÉ · HÌNH THỨC'] as $cell => $value) {
            $journal->setCellValue($cell, $value);
        }
        $date = new \DateTimeImmutable('2026-11-10');
        $values = [
            'A5' => Date::PHPToExcel($date), 'B5' => 'CT-01-10/11',
            'C5' => 'HDV Test', 'E5' => 20, 'F5' => 0, 'G5' => 20, 'H5' => 10, 'I5' => 'Tiền mặt',
            'S5' => 5, 'W5' => 1, 'Y5' => 1, 'AC5' => 1000000, 'AD5' => 400000, 'AE5' => 280000, 'AF5' => 0, 'AG5' => 1280000,
            'AH5' => 540000, 'AI5' => 100000, 'AJ5' => 50000, 'AK5' => 30000, 'AL5' => 0,
            'AM5' => 0, 'AN5' => 1120000, 'AO5' => 160000, 'AT5' => 0, 'CA5' => 85000,
            'CB5' => 165000, 'CC5' => 1000000, 'CD5' => 1000000, 'CE5' => 0, 'CF5' => 0,
            'CG5' => 280000, 'CH5' => 160000, 'CI5' => 0,
        ];
        foreach ($values as $cell => $value) {
            $journal->setCellValue($cell, $value);
        }
        $journal->setCellValueExplicit('D5', '0901234567', DataType::TYPE_STRING);

        $prices = $spreadsheet->createSheet();
        $prices->setTitle('BANG GIA');
        foreach ([4 => 50000, 5 => 60000, 6 => 30000, 23 => 200000, 24 => 150000, 25 => 100000, 26 => 50000, 27 => 100000, 31 => 20000, 33 => 40000, 35 => 180000, 36 => 120000, 41 => 75000, 42 => 40000, 43 => 10000, 44 => 5000, 46 => 100000, 47 => 70000, 48 => 120000, 49 => 100000, 50 => 10000] as $row => $value) {
            $prices->setCellValue('B'.$row, $value);
        }
        $spreadsheet->createSheet()->setTitle('LUU CHI PHI CU');

        $temporaryPath = tempnam(sys_get_temp_dir(), 'cao-thi-import-');
        (new Xlsx($spreadsheet))->save($temporaryPath);
        $spreadsheet->disconnectWorksheets();

        if ($macroEnabled) {
            $contents = $this->asMacroEnabledWorkbook($temporaryPath);
        } else {
            $contents = file_get_contents($temporaryPath);
        }

        unlink($temporaryPath);

        return UploadedFile::fake()->createWithContent('history.xlsm', $contents);
    }

    private function asMacroEnabledWorkbook(string $xlsxPath): string
    {
        $source = new \ZipArchive;
        $this->assertTrue($source->open($xlsxPath, \ZipArchive::CHECKCONS) === true);
        $targetPath = tempnam(sys_get_temp_dir(), 'cao-thi-xlsm-');
        $target = new \ZipArchive;
        $this->assertTrue($target->open($targetPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === true);

        for ($index = 0; $index < $source->numFiles; $index++) {
            $name = $source->getNameIndex($index);
            $contents = $source->getFromIndex($index);
            if ($name === '[Content_Types].xml') {
                $contents = str_replace(
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml',
                    'application/vnd.ms-excel.sheet.macroEnabled.main+xml',
                    $contents,
                );
            }
            $target->addFromString($name, $contents);
        }

        $source->close();
        $target->close();
        $contents = file_get_contents($targetPath);
        unlink($targetPath);

        return $contents;
    }
}
