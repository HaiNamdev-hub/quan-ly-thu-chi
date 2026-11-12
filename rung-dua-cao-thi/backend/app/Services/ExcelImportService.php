<?php

namespace App\Services;

use App\Models\ExcelImportBatch;
use App\Models\Guide;
use App\Models\TourGroup;
use App\Models\TourService as TourServiceLine;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ExcelImportService
{
    private const MAIN_SHEET = 'NHAT KY DOAN';

    /** @var array<string, string> */
    private const COLUMN_NAMES = [
        'A' => 'Ngày', 'B' => 'Mã đoàn', 'C' => 'Tên HDV', 'D' => 'SĐT HDV',
        'E' => 'Khách Việt', 'F' => 'Khách quốc tế', 'G' => 'Tổng khách', 'H' => 'Số thúng',
        'I' => 'Hình thức thu vé', 'L' => 'Revive', 'M' => 'Fanta', 'N' => 'Seven Up',
        'O' => 'Bò húc', 'P' => 'Aquafina', 'Q' => 'Number One', 'R' => 'Dừa trái',
        'S' => 'Chè dừa', 'T' => 'Cà phê sữa', 'U' => 'Cà phê đen', 'V' => 'Bia Tiger',
        'W' => 'Ảnh khung to', 'X' => 'Ảnh khung nhỏ', 'Y' => 'Ảnh plastic lớn', 'Z' => 'Ảnh plastic nhỏ',
        'AA' => 'File mềm', 'AB' => 'Áo dài', 'AC' => 'Thu vé', 'AD' => 'Chi công chèo',
        'AE' => 'Thu ảnh', 'AF' => 'Thu áo dài', 'AG' => 'Tổng thu', 'AH' => 'Chi thúng',
        'AI' => 'Hoa hồng cửa', 'AJ' => 'Hoa hồng chè dừa', 'AK' => 'Hoa hồng ảnh', 'AL' => 'Hoa hồng áo dài',
        'AM' => 'Chi nước uống trích HDV', 'AN' => 'Tổng chi', 'AO' => 'Lãi gộp', 'AP' => 'Ghi chú',
        'AT' => 'Doanh thu chè dừa', 'CA' => 'Giá vốn ảnh', 'CB' => 'Lãi sau giá vốn ảnh',
        'CC' => 'Vé đã chọn hình thức thanh toán', 'CD' => 'Vé tiền mặt', 'CE' => 'Vé CK công ty',
        'CF' => 'Vé CK cá nhân', 'CG' => 'Thu ảnh và áo dài tiền mặt', 'CH' => 'Tiền két dự kiến',
        'CI' => 'Vé chưa phân loại',
    ];

    /** @param array<string, mixed> $sourceValues @return array<string, mixed> */
    public function upload(UploadedFile $file, User $actor): ExcelImportBatch
    {
        $hash = hash_file('sha256', $file->getRealPath());
        $existing = ExcelImportBatch::query()->where('source_sha256', $hash)->first();
        if ($existing !== null) {
            return $existing;
        }

        $path = $file->store('excel-imports', 'local');

        return ExcelImportBatch::query()->create([
            'source_name' => $file->getClientOriginalName(),
            'source_sha256' => $hash,
            'storage_path' => $path,
            'status' => 'uploaded',
            'source_sheet' => self::MAIN_SHEET,
            'imported_by' => $actor->id,
        ]);
    }

    public function analyze(ExcelImportBatch $batch): ExcelImportBatch
    {
        if ($batch->status === 'confirmed') {
            throw ValidationException::withMessages(['batch' => ['Lô dữ liệu đã xác nhận, không thể phân tích lại.']]);
        }

        $filePath = Storage::disk('local')->path($batch->storage_path);
        $reader = IOFactory::createReaderForFile($filePath);
        $reader->setReadDataOnly(false);
        $spreadsheet = $reader->load($filePath);
        try {
            if (! $spreadsheet->sheetNameExists(self::MAIN_SHEET)) {
                throw ValidationException::withMessages(['file' => ['Không tìm thấy sheet NHAT KY DOAN.']]);
            }

            $sheet = $spreadsheet->getSheetByName(self::MAIN_SHEET);
            $seenKeys = [];
            $seenSequences = [];
            $ready = 0;
            $issuesCount = 0;
            $sourceTotals = $this->emptySnapshotTotals();
            $readyTotals = $this->emptySnapshotTotals();
            $records = [];
            $legacyExpenses = $spreadsheet->sheetNameExists('LUU CHI PHI CU')
                ? $this->readArchiveRows($spreadsheet->getSheetByName('LUU CHI PHI CU'))
                : [];
            $photoPrices = $spreadsheet->sheetNameExists('BANG GIA')
                ? $this->readPhotoPrices($spreadsheet->getSheetByName('BANG GIA'))
                : [];
            $headerIssues = $this->validateHeaders($sheet);
            for ($rowNumber = 5; $rowNumber <= min(505, $sheet->getHighestDataRow()); $rowNumber++) {
                $source = $this->readSourceRow($sheet, $rowNumber);
                if ($this->isEmptyRow($source)) {
                    continue;
                }
                $record = $this->mapSourceRow($source, $photoPrices);
                $issues = $this->validateMappedRow($source, $record);
                $matchingGuide = $record['guide_phone'] !== '' ? Guide::withTrashed()->where('phone', $record['guide_phone'])->first() : null;
                if ($matchingGuide !== null && (mb_strtolower(trim($matchingGuide->name)) !== mb_strtolower(trim($record['guide_name'])) || $matchingGuide->trashed())) {
                    $issues[] = 'Số điện thoại HDV khớp hồ sơ có tên khác hoặc đã lưu trữ; cần chọn HDV thủ công.';
                }
                array_push($issues, ...$headerIssues);
                $identity = ($record['tour_date'] ?? '').'|'.($record['code'] ?? '');
                $sequenceIdentity = ($record['tour_date'] ?? '').'|'.($record['sequence'] ?? '');
                if ($identity !== '|' && isset($seenKeys[$identity])) {
                    $issues[] = 'Trùng ngày và mã đoàn trong cùng workbook.';
                }
                if ($sequenceIdentity !== '|' && isset($seenSequences[$sequenceIdentity])) {
                    $issues[] = 'Trùng ngày và số thứ tự đoàn trong cùng workbook.';
                }
                $seenKeys[$identity] = true;
                $seenSequences[$sequenceIdentity] = true;

                if (($record['tour_date'] ?? null) !== null && ($record['code'] ?? null) !== null
                    && TourGroup::withTrashed()->whereDate('tour_date', $record['tour_date'])
                        ->where(fn ($query) => $query->where('code', $record['code'])->orWhere('sequence', $record['sequence']))->exists()) {
                    $issues[] = 'Ngày và mã hoặc số thứ tự đoàn đã có trong hệ thống; hệ thống không ghi đè.';
                }

                $records[] = [$rowNumber, $source, $record, array_values(array_unique($issues))];
                $this->accumulateSnapshotTotals($sourceTotals, $source);
                $issuesCount += count($issues);
                if ($issues === []) {
                    $ready++;
                    $this->accumulateSnapshotTotals($readyTotals, $source);
                }
            }

            DB::transaction(function () use ($batch, $records, $legacyExpenses, $ready, $issuesCount, $sourceTotals, $readyTotals): void {
                $batch->rows()->delete();
                DB::table('excel_import_archives')->where('excel_import_batch_id', $batch->id)->delete();
                foreach ($records as [$rowNumber, $source, $record, $issues]) {
                    $batch->rows()->create([
                        'source_sheet' => self::MAIN_SHEET,
                        'source_row' => $rowNumber,
                        'row_sha256' => hash('sha256', json_encode($source, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)),
                        'status' => $issues === [] ? 'ready' : 'needs_review',
                        'source_values' => $source,
                        'mapped_values' => $record,
                        'issues' => $issues,
                    ]);
                }
                foreach ($legacyExpenses as [$rowNumber, $source]) {
                    DB::table('excel_import_archives')->insert([
                        'excel_import_batch_id' => $batch->id,
                        'source_sheet' => 'LUU CHI PHI CU',
                        'source_row' => $rowNumber,
                        'record_type' => 'legacy_expense',
                        'source_values' => json_encode($source, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                        'status' => 'archived_not_dashboard',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
                $batch->forceFill([
                    'status' => 'analyzed',
                    'analyzed_at' => now(),
                    'summary' => [
                        'total_rows' => count($records), 'ready_rows' => $ready,
                        'rows_needing_review' => count($records) - $ready, 'issue_count' => $issuesCount,
                        'archived_legacy_expense_rows' => count($legacyExpenses),
                        'source_totals' => $sourceTotals, 'ready_totals' => $readyTotals,
                    ],
                ])->save();
            });
        } finally {
            $spreadsheet->disconnectWorksheets();
        }

        return $batch->refresh();
    }

    /** @return array{imported: int, skipped: int} */
    public function confirm(ExcelImportBatch $batch, User $actor): array
    {
        if ($batch->status !== 'analyzed') {
            throw ValidationException::withMessages(['batch' => ['Hãy phân tích lô trước khi xác nhận import.']]);
        }

        return DB::transaction(function () use ($batch, $actor): array {
            $imported = 0;
            $skipped = 0;
            $rows = $batch->rows()->where('status', 'ready')->lockForUpdate()->get();
            if ($rows->isEmpty()) {
                throw ValidationException::withMessages(['batch' => ['Không có dòng nào sẵn sàng để import.']]);
            }
            foreach ($rows as $row) {
                $data = $row->mapped_values;
                $exists = TourGroup::withTrashed()->whereDate('tour_date', $data['tour_date'])
                    ->where(fn ($query) => $query->where('code', $data['code'])->orWhere('sequence', $data['sequence']))->exists();
                if ($exists) {
                    $row->update(['status' => 'conflict', 'issues' => ['Ngày và mã đoàn đã tồn tại; bản ghi được giữ nguyên.']]);
                    $skipped++;

                    continue;
                }

                $guide = null;
                if (! empty($data['guide_phone'])) {
                    $guide = Guide::withTrashed()->where('phone', $data['guide_phone'])->first();
                }
                if ($guide === null && ! empty($data['guide_name'])) {
                    $guide = Guide::query()->create([
                        'name' => $data['guide_name'], 'phone' => $data['guide_phone'] ?: null,
                        'notes' => 'Nguồn import Excel: '.self::MAIN_SHEET.' dòng '.$row->source_row,
                        'is_active' => true,
                    ]);
                }

                $tour = TourGroup::query()->create([
                    'tour_date' => $data['tour_date'], 'sequence' => $data['sequence'], 'code' => $data['code'],
                    'guide_id' => $guide?->id, 'created_by' => $actor->id, 'updated_by' => $actor->id,
                    'vietnamese_guests' => $data['vietnamese_guests'], 'international_guests' => $data['international_guests'],
                    'total_guests' => $data['total_guests'], 'paid_tickets' => $data['paid_tickets'], 'boats' => $data['boats'],
                    'ticket_vietnamese_unit_price' => $data['ticket_vietnamese_unit_price'],
                    'ticket_international_unit_price' => $data['ticket_international_unit_price'],
                    'ticket_cost_unit_price' => $data['ticket_cost_unit_price'],
                    'boat_cost_unit_price' => $data['boat_cost_unit_price'],
                    'ticket_revenue' => $data['ticket_revenue'], 'ticket_cost' => $data['ticket_cost'], 'boat_cost' => $data['boat_cost'],
                    'door_commission' => $data['door_commission'], 'coconut_portions' => $data['coconut_portions'],
                    'coconut_commission_unit_price' => $data['coconut_commission_unit_price'],
                    'ao_dai_units' => $data['ao_dai_units'],
                    'ao_dai_commission_unit_price' => $data['ao_dai_commission_unit_price'],
                    'ao_dai_revenue' => $data['ao_dai_revenue'],
                    'photo_revenue' => $data['photo_revenue'], 'photo_cost' => $data['photo_cost'],
                    'photo_commission' => $data['photo_commission'], 'other_expenses' => $data['other_expenses'],
                    'ticket_payment_method' => $data['ticket_payment_method'], 'status' => 'APPROVED',
                    'approved_by' => $actor->id,
                    'notes' => $data['notes'], 'source_type' => TourGroup::SOURCE_REAL_IMPORTED, 'is_reconciled' => true,
                    'counts_in_dashboard' => true, 'excel_import_row_id' => $row->id,
                ]);
                TourServiceLine::query()->create([
                    'tour_group_id' => $tour->id, 'service_type' => 'coconut', 'quantity' => $data['coconut_portions'],
                    'revenue' => 0, 'commission_unit_price' => $data['coconut_commission_unit_price'],
                    'commission_amount' => $data['coconut_commission'],
                ]);
                TourServiceLine::query()->create([
                    'tour_group_id' => $tour->id, 'service_type' => 'ao_dai', 'quantity' => $data['ao_dai_units'],
                    'revenue' => $data['ao_dai_revenue'], 'commission_unit_price' => $data['ao_dai_commission_unit_price'],
                    'commission_amount' => $data['ao_dai_commission'],
                ]);
                foreach ($data['other_service_quantities'] as $serviceType => $quantity) {
                    if ($quantity > 0) {
                        TourServiceLine::query()->create([
                            'tour_group_id' => $tour->id,
                            'service_type' => $serviceType,
                            'quantity' => $quantity,
                            'revenue' => 0,
                            'commission_unit_price' => 0,
                            'commission_amount' => 0,
                        ]);
                    }
                }
                foreach ($data['photo_lines'] as $photoLine) {
                    $tour->photos()->create($photoLine);
                }
                $row->update(['status' => 'imported', 'tour_group_id' => $tour->id]);
                $imported++;
            }

            $batch->forceFill(['status' => 'confirmed', 'confirmed_at' => now()])->save();

            return ['imported' => $imported, 'skipped' => $skipped];
        });
    }

    public function includeInDashboard(ExcelImportBatch $batch, User $actor): int
    {
        if ($batch->status !== 'confirmed') {
            throw ValidationException::withMessages(['batch' => ['Chỉ lô đã import mới có thể bật vào Dashboard.']]);
        }

        return DB::transaction(function () use ($batch, $actor): int {
            $tourIds = $batch->rows()->where('status', 'imported')->whereNotNull('tour_group_id')->pluck('tour_group_id');

            return TourGroup::query()->whereIn('id', $tourIds)->whereIn('source_type', [TourGroup::SOURCE_REAL_IMPORTED, 'excel'])
                ->where('counts_in_dashboard', false)
                ->update([
                    'status' => 'APPROVED',
                    'approved_by' => $actor->id,
                    'updated_by' => $actor->id,
                    'source_type' => TourGroup::SOURCE_REAL_IMPORTED,
                    'is_reconciled' => true,
                    'counts_in_dashboard' => true,
                ]);
        });
    }

    /** @return array<string, mixed> */
    private function readSourceRow(Worksheet $sheet, int $rowNumber): array
    {
        $source = [];
        foreach (self::COLUMN_NAMES as $column => $label) {
            $cell = $sheet->getCell($column.$rowNumber);
            $raw = $cell->getValue();
            $formula = is_string($raw) && str_starts_with($raw, '=');
            $value = $formula ? $cell->getOldCalculatedValue() : $raw;
            if ($column === 'D' && $value !== null && $value !== '') {
                $value = $cell->getFormattedValue();
            }
            if ($column === 'A' && is_numeric($value)) {
                $value = ExcelDate::excelToDateTimeObject((float) $value)->format('Y-m-d');
            }
            $source[$column] = [
                'label' => $label,
                'value' => is_scalar($value) ? $value : null,
                'formula' => $formula,
                'formula_missing_cache' => $formula && $value === null,
                'formula_text' => $formula ? (string) $raw : null,
            ];
        }

        return $source;
    }

    /** @param array<string, array{label: string, value: mixed, formula: bool, formula_missing_cache: bool, formula_text: ?string}> $source */
    private function isEmptyRow(array $source): bool
    {
        return empty($source['A']['value']) && empty($source['B']['value']) && empty($source['C']['value']) && empty($source['D']['value']);
    }

    /** @return list<array{int, array<string, array{value: mixed, formula: bool, formula_missing_cache: bool, formula_text: ?string}>}> */
    private function readArchiveRows(Worksheet $sheet): array
    {
        $records = [];
        for ($rowNumber = 1; $rowNumber <= $sheet->getHighestDataRow(); $rowNumber++) {
            $values = [];
            $hasValue = false;
            $highestColumn = Coordinate::columnIndexFromString($sheet->getHighestDataColumn());
            for ($columnNumber = 1; $columnNumber <= $highestColumn; $columnNumber++) {
                $column = Coordinate::stringFromColumnIndex($columnNumber);
                $cell = $sheet->getCell($column.$rowNumber);
                $raw = $cell->getValue();
                $formula = is_string($raw) && str_starts_with($raw, '=');
                $value = $formula ? $cell->getOldCalculatedValue() : $raw;
                $hasValue = $hasValue || ($value !== null && $value !== '');
                $values[$column] = [
                    'value' => is_scalar($value) ? $value : null,
                    'formula' => $formula,
                    'formula_missing_cache' => $formula && $value === null,
                    'formula_text' => $formula ? (string) $raw : null,
                ];
            }
            $hasExpenseAmount = isset($values['A']['value'], $values['E']['value'])
                && is_numeric($values['A']['value']) && is_numeric($values['E']['value']);
            if ($hasValue && $hasExpenseAmount) {
                $records[] = [$rowNumber, $values];
            }
        }

        return $records;
    }

    /** @param array<string, array{label: string, value: mixed, formula: bool, formula_missing_cache: bool, formula_text: ?string}> $source
     * @return array<string, mixed>
     */
    private function mapSourceRow(array $source, array $photoPrices): array
    {
        $v = static fn (string $column): mixed => $source[$column]['value'] ?? null;
        $date = $v('A');
        $code = trim((string) $v('B'));
        preg_match('/^CT-(\d+)-/', $code, $matches);
        $payment = match (trim((string) $v('I'))) {
            'Tiền mặt' => 'cash', 'CK công ty' => 'company_transfer', 'CK cá nhân' => 'personal_transfer', default => null,
        };

        $record = [
            'tour_date' => is_string($date) ? $date : null, 'code' => $code ?: null,
            'sequence' => isset($matches[1]) ? (int) $matches[1] : null,
            'guide_name' => trim((string) $v('C')), 'guide_phone' => trim((string) $v('D')),
            'ticket_vietnamese_unit_price' => $photoPrices[4] ?? null,
            'ticket_international_unit_price' => $photoPrices[5] ?? null,
            'ticket_cost_unit_price' => $photoPrices[6] ?? null,
            'boat_cost_unit_price' => $photoPrices[33] ?? null,
            'vietnamese_guests' => (int) ($v('E') ?? 0), 'international_guests' => (int) ($v('F') ?? 0),
            'total_guests' => $this->nullableInt($source, 'G'),
            'paid_tickets' => $this->nullableInt($source, 'G') !== null ? (int) $v('G') - intdiv((int) $v('G'), 10) : null,
            'boats' => $this->nullableInt($source, 'H'), 'ticket_revenue' => $this->nullableInt($source, 'AC'),
            'ticket_cost' => $this->nullableInt($source, 'AH'), 'boat_cost' => $this->nullableInt($source, 'AD'),
            'door_commission' => $this->nullableInt($source, 'AI'), 'coconut_portions' => (int) ($v('S') ?? 0),
            'coconut_commission_unit_price' => $this->unitCommission($source, 'AJ', 'S'),
            'coconut_commission' => $this->nullableInt($source, 'AJ'), 'ao_dai_units' => (int) ($v('AB') ?? 0),
            'ao_dai_revenue' => $this->nullableInt($source, 'AF'), 'ao_dai_commission' => $this->nullableInt($source, 'AL'),
            'ao_dai_commission_unit_price' => $this->unitCommission($source, 'AL', 'AB'),
            'photo_revenue' => $this->nullableInt($source, 'AE'), 'photo_cost' => $this->nullableInt($source, 'CA'),
            'photo_commission' => $this->nullableInt($source, 'AK'), 'other_expenses' => $this->nullableInt($source, 'AM'),
            'ticket_payment_method' => $payment, 'notes' => trim((string) $v('AP')) ?: null,
            'other_service_quantities' => [
                'revive' => (int) ($v('L') ?? 0), 'fanta' => (int) ($v('M') ?? 0),
                'seven_up' => (int) ($v('N') ?? 0), 'energy_drink' => (int) ($v('O') ?? 0),
                'bottled_water' => (int) ($v('P') ?? 0), 'number_one' => (int) ($v('Q') ?? 0),
                'fresh_coconut' => (int) ($v('R') ?? 0), 'coffee_milk' => (int) ($v('T') ?? 0),
                'coffee_black' => (int) ($v('U') ?? 0), 'tiger_beer' => (int) ($v('V') ?? 0),
            ],
        ];
        $record['photo_lines'] = $this->mapPhotoLines($source, $photoPrices, $record);

        return $record;
    }

    /** @return array<string, int> */
    private function readPhotoPrices(Worksheet $sheet): array
    {
        $prices = [];
        foreach ([4, 5, 6, 23, 24, 25, 26, 27, 31, 33, 35, 36, 41, 42, 43, 44, 46, 47, 48, 49, 50] as $row) {
            $value = $sheet->getCell('B'.$row)->getValue();
            if (! is_numeric($value)) {
                return [];
            }
            $prices[$row] = (int) $value;
        }

        return $prices;
    }

    /** @param array<string, array{label: string, value: mixed, formula: bool, formula_missing_cache: bool, formula_text: ?string}> $source
     * @param  array<int, int>  $prices
     * @param  array<string, mixed>  $record
     * @return list<array{photo_type: string, quantity: int, unit_price_snapshot: int, unit_cost_snapshot: int, revenue: int, cost: int, commission: int}>
     */
    private function mapPhotoLines(array $source, array $prices, array $record): array
    {
        if ($prices === []) {
            return [];
        }
        $date = $record['tour_date'];
        $newPrices = $date >= '2026-11-09';
        $internationalPrice = $record['international_guests'] > 0;
        $types = [
            'large_frame' => ['W', 'frame', 35, 23, 41],
            'small_frame' => ['X', 'frame', 36, 24, 42],
            'large_plastic' => ['Y', 'plastic_large', 46, 48, 43],
            'small_plastic' => ['Z', 'plastic_small', 47, 49, 44],
            'soft_file' => ['AA', 'soft', 27, 27, null],
        ];
        $lines = [];
        foreach ($types as $type => [$column, $kind, $vietnameseNewKey, $internationalKey, $costKey]) {
            $quantity = (int) ($source[$column]['value'] ?? 0);
            if ($quantity === 0) {
                continue;
            }
            $priceKey = match (true) {
                $kind === 'soft' => 27,
                $kind === 'frame' && ! $internationalPrice => $vietnameseNewKey,
                $kind === 'plastic_large' && $newPrices && ! $internationalPrice => $vietnameseNewKey,
                $kind === 'plastic_small' && $newPrices && ! $internationalPrice => $vietnameseNewKey,
                ! $newPrices && str_starts_with($type, 'large_plastic') => 25,
                ! $newPrices && str_starts_with($type, 'small_plastic') => 26,
                default => $internationalKey,
            };
            $commissionUnit = $kind === 'soft' ? 0 : ((($kind === 'plastic_large' || $kind === 'plastic_small') && $newPrices) ? $prices[50] : $prices[31]);
            $unitCost = $costKey === null ? 0 : $prices[$costKey];
            $lines[] = [
                'photo_type' => $type, 'quantity' => $quantity, 'unit_price_snapshot' => $prices[$priceKey],
                'unit_cost_snapshot' => $unitCost, 'revenue' => $quantity * $prices[$priceKey],
                'cost' => $quantity * $unitCost, 'commission' => $quantity * $commissionUnit,
            ];
        }

        return $lines;
    }

    private function unitCommission(array $source, string $amountColumn, string $quantityColumn): int
    {
        $quantity = (int) ($source[$quantityColumn]['value'] ?? 0);
        $amount = $source[$amountColumn]['value'] ?? null;

        return $quantity > 0 && is_numeric($amount) && (int) $amount % $quantity === 0 ? intdiv((int) $amount, $quantity) : 0;
    }

    /** @return list<string> */
    private function validateHeaders(Worksheet $sheet): array
    {
        $expected = ['A4' => 'Ngày', 'B4' => 'Mã đoàn', 'C4' => 'Tên HDV', 'D4' => 'SĐT HDV', 'E4' => 'Khách Việt', 'F4' => 'Khách quốc tế', 'I4' => 'THU TIỀN VÉ · HÌNH THỨC'];
        $issues = [];
        foreach ($expected as $cell => $label) {
            if (trim((string) $sheet->getCell($cell)->getValue()) !== $label) {
                $issues[] = "Sai tiêu đề cột {$cell}; file không khớp mẫu NHAT KY DOAN đã đối chiếu.";
            }
        }

        return $issues;
    }

    /** @param array<string, array{value: mixed, formula_missing_cache: bool}> $source */
    private function nullableInt(array $source, string $column): ?int
    {
        $value = $source[$column]['value'] ?? null;

        return $value === null || $value === '' ? null : (int) $value;
    }

    /** @return array<string, int> */
    private function emptySnapshotTotals(): array
    {
        return array_fill_keys([
            'ticket_revenue', 'photo_revenue', 'ao_dai_revenue', 'total_revenue', 'ticket_cost',
            'boat_cost', 'door_commission', 'coconut_commission', 'photo_commission', 'ao_dai_commission',
            'other_expenses', 'total_expenses', 'gross_profit', 'photo_cost', 'profit_after_photo_cost',
        ], 0);
    }

    /** @param array<string, int> $totals
     * @param  array<string, array{value: mixed, formula: bool, formula_missing_cache: bool, formula_text: ?string}>  $source
     */
    private function accumulateSnapshotTotals(array &$totals, array $source): void
    {
        $columns = [
            'ticket_revenue' => 'AC', 'photo_revenue' => 'AE', 'ao_dai_revenue' => 'AF',
            'total_revenue' => 'AG', 'ticket_cost' => 'AH', 'boat_cost' => 'AD',
            'door_commission' => 'AI', 'coconut_commission' => 'AJ', 'photo_commission' => 'AK',
            'ao_dai_commission' => 'AL', 'other_expenses' => 'AM', 'total_expenses' => 'AN',
            'gross_profit' => 'AO', 'photo_cost' => 'CA', 'profit_after_photo_cost' => 'CB',
        ];
        foreach ($columns as $key => $column) {
            $value = $source[$column]['value'] ?? null;
            if (is_numeric($value)) {
                $totals[$key] += (int) $value;
            }
        }
    }

    /** @param array<string, array{label: string, value: mixed, formula: bool, formula_missing_cache: bool, formula_text: ?string}> $source
     * @param  array<string, mixed>  $record
     * @return list<string>
     */
    private function validateMappedRow(array $source, array $record): array
    {
        $issues = [];
        foreach (['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'S', 'W', 'X', 'Y', 'Z', 'AA', 'AB', 'AC', 'AD', 'AE', 'AF', 'AG', 'AH', 'AI', 'AJ', 'AK', 'AL', 'AM', 'AN', 'AO', 'AT', 'CA', 'CB', 'CC', 'CD', 'CE', 'CF', 'CG', 'CH', 'CI'] as $column) {
            if (($source[$column]['formula_missing_cache'] ?? false) === true) {
                $issues[] = 'Ô '.$column.' có công thức nhưng thiếu giá trị lưu trong Excel; cần đối soát thủ công.';
            }
        }
        foreach (['tour_date', 'code', 'sequence', 'total_guests', 'paid_tickets', 'boats', 'ticket_revenue', 'ticket_cost', 'boat_cost', 'door_commission', 'coconut_commission', 'ao_dai_revenue', 'ao_dai_commission', 'photo_revenue', 'photo_cost', 'photo_commission', 'other_expenses', 'ticket_vietnamese_unit_price', 'ticket_international_unit_price', 'ticket_cost_unit_price', 'boat_cost_unit_price'] as $key) {
            if ($record[$key] === null) {
                $issues[] = "Thiếu giá trị nguồn cho {$key}; không tự tính hoặc thay bằng 0.";
            }
        }
        if ($record['guide_name'] === '') {
            $issues[] = 'Thiếu tên HDV.';
        }
        if ($record['guide_phone'] === '' || preg_match('/^0\d{9}$/', $record['guide_phone']) !== 1) {
            $issues[] = 'Số điện thoại HDV thiếu hoặc không đúng định dạng 10 chữ số; kiểm tra số 0 đầu.';
        }
        if (! is_string($record['tour_date']) || preg_match('/^202[0-9]-\d{2}-\d{2}$/', $record['tour_date']) !== 1
            || ! is_string($record['code']) || preg_match('/^CT-\d{2}-\d{2}\/\d{2}$/', $record['code']) !== 1
            || (int) ($record['sequence'] ?? 0) < 1 || (int) ($record['sequence'] ?? 0) > 20) {
            $issues[] = 'Ngày hoặc mã đoàn không khớp định dạng lịch sử; cần đối soát.';
        }
        if ($record['coconut_portions'] > 0 && $record['coconut_commission'] === null) {
            $issues[] = 'Có số lượng chè dừa nhưng thiếu snapshot hoa hồng; cần đối soát.';
        }
        $photoTotals = ['revenue' => 0, 'cost' => 0, 'commission' => 0];
        foreach ($record['photo_lines'] as $line) {
            $photoTotals['revenue'] += $line['revenue'];
            $photoTotals['cost'] += $line['cost'];
            $photoTotals['commission'] += $line['commission'];
        }
        foreach (['revenue' => 'photo_revenue', 'cost' => 'photo_cost', 'commission' => 'photo_commission'] as $totalKey => $recordKey) {
            if ($record[$recordKey] !== null && $photoTotals[$totalKey] !== $record[$recordKey]) {
                $issues[] = "Chi tiết ảnh không khớp snapshot {$recordKey}; cần đối soát.";
            }
        }

        return $issues;
    }
}
