<?php

namespace Tests\Feature;

use App\Models\ExcelImportBatch;
use App\Models\Expense;
use App\Models\TourGroup;
use App\Models\User;
use App\Role;
use App\Services\TourService;
use Database\Seeders\BusinessRulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_financial_summary_uses_only_approved_tours_and_keeps_unclassified_separate(): void
    {
        $this->seed(BusinessRulesSeeder::class);
        $manager = User::factory()->create(['role' => Role::Manager]);
        $tour = app(TourService::class)->create([
            'tour_date' => '2026-11-11', 'vietnamese_guests' => 20, 'international_guests' => 0,
            'ticket_payment_method' => null, 'coconut_portions' => 5, 'ao_dai_units' => 1, 'ao_dai_revenue' => 50000,
        ], $manager);
        $tour->update(['status' => 'APPROVED']);

        $this->actingAs($manager)->getJson('/api/dashboard/summary?mode=day&date=2026-11-11')
            ->assertOk()
            ->assertJsonPath('summary.ticket_revenue', 1000000)
            ->assertJsonPath('summary.ticket_unclassified', 1000000)
            ->assertJsonPath('summary.ticket_cash', 0)
            ->assertJsonPath('summary.revenue', 1050000)
            ->assertJsonPath('summary.operating_expenses', 1110000)
            ->assertJsonPath('summary.profit_after_photo_cost', -60000)
            ->assertJsonPath('cash_reconciliation.variance', 0)
            ->assertJsonPath('cash_reconciliation.is_actual_balance', false);
    }

    public function test_staff_dashboard_does_not_include_financial_fields(): void
    {
        $staff = User::factory()->create(['role' => Role::Staff]);

        $this->actingAs($staff)->getJson('/api/dashboard/summary')
            ->assertOk()
            ->assertJsonStructure(['operations' => ['tour_count', 'total_guests', 'photos_sold', 'incomplete_tours', 'my_tours']])
            ->assertJsonMissingPath('summary.profit_after_photo_cost');

        $this->actingAs($staff)->getJson('/api/dashboard/cash-reconciliation')->assertForbidden();
    }

    public function test_dashboard_includes_separate_expenses_and_excludes_soft_deleted_expenses(): void
    {
        $this->seed(BusinessRulesSeeder::class);
        $manager = User::factory()->create(['role' => Role::Manager]);
        $tour = app(TourService::class)->create([
            'tour_date' => '2026-11-11', 'vietnamese_guests' => 20, 'international_guests' => 0,
            'ticket_payment_method' => 'cash',
        ], $manager);
        $tour->update(['status' => 'APPROVED']);
        $expense = Expense::create([
            'category' => 'Sửa chữa', 'amount' => 25000, 'occurred_on' => '2026-11-11',
            'tour_group_id' => $tour->id, 'created_by' => $manager->id,
        ]);

        $this->actingAs($manager)->getJson('/api/dashboard/summary?mode=day&date=2026-11-11')
            ->assertOk()->assertJsonPath('summary.other_expenses', 25000);

        $expense->delete();
        $this->actingAs($manager)->getJson('/api/dashboard/summary?mode=day&date=2026-11-11')
            ->assertOk()->assertJsonPath('summary.other_expenses', 0);
    }

    public function test_company_transfer_ticket_revenue_is_not_counted_as_cash(): void
    {
        $this->seed(BusinessRulesSeeder::class);
        $manager = User::factory()->create(['role' => Role::Manager]);
        $tour = app(TourService::class)->create([
            'tour_date' => '2026-11-11', 'vietnamese_guests' => 40, 'international_guests' => 0,
            'ticket_payment_method' => 'company_transfer',
        ], $manager);
        $tour->update(['status' => 'LOCKED']);

        $this->actingAs($manager)->getJson('/api/dashboard/summary?mode=day&date=2026-11-11')
            ->assertOk()
            ->assertJsonPath('summary.ticket_revenue', 2000000)
            ->assertJsonPath('summary.ticket_cash', 0)
            ->assertJsonPath('summary.company_transfer', 2000000)
            ->assertJsonPath('cash_reconciliation.variance', 0);
    }

    public function test_report_does_not_include_tours_after_the_selected_report_date(): void
    {
        $this->seed(BusinessRulesSeeder::class);
        $manager = User::factory()->create(['role' => Role::Manager]);
        $service = app(TourService::class);
        foreach (['2026-11-11', '2026-11-12'] as $date) {
            $tour = $service->create([
                'tour_date' => $date, 'vietnamese_guests' => 2, 'international_guests' => 0,
                'ticket_payment_method' => 'cash',
            ], $manager);
            $tour->update(['status' => 'APPROVED']);
        }

        $this->actingAs($manager)->getJson('/api/dashboard/summary?mode=month&date=2026-11-11')
            ->assertOk()
            ->assertJsonPath('period.to', '2026-11-11')
            ->assertJsonPath('summary.tour_count', 1)
            ->assertJsonPath('summary.ticket_revenue', 100000);
    }

    public function test_dashboard_reports_unconfirmed_and_unreconciled_import_rows_separately(): void
    {
        $this->seed(BusinessRulesSeeder::class);
        $manager = User::factory()->create(['role' => Role::Manager]);
        $pendingTour = app(TourService::class)->create([
            'tour_date' => '2026-11-11', 'vietnamese_guests' => 20, 'international_guests' => 0,
        ], $manager);
        $pendingTour->update([
            'status' => 'PENDING', 'source_type' => TourGroup::SOURCE_REAL_IMPORTED,
            'is_reconciled' => false, 'counts_in_dashboard' => false,
        ]);
        $batch = ExcelImportBatch::query()->create([
            'source_name' => 'pending.xlsm', 'source_sha256' => hash('sha256', 'pending-workbook'),
            'storage_path' => 'excel-imports/pending.xlsm', 'status' => 'analyzed',
            'source_sheet' => 'NHAT KY DOAN', 'imported_by' => $manager->id,
        ]);
        $batch->rows()->createMany([
            [
                'source_sheet' => 'NHAT KY DOAN', 'source_row' => 5, 'row_sha256' => str_repeat('a', 64),
                'status' => 'ready', 'source_values' => [], 'mapped_values' => ['tour_date' => '2026-11-11'],
                'issues' => [],
            ],
            [
                'source_sheet' => 'NHAT KY DOAN', 'source_row' => 6, 'row_sha256' => str_repeat('b', 64),
                'status' => 'needs_review', 'source_values' => [], 'mapped_values' => ['tour_date' => '2026-11-11'],
                'issues' => ['snapshot mismatch'],
            ],
        ]);

        $this->actingAs($manager)->getJson('/api/dashboard/summary?mode=day&date=2026-11-11')
            ->assertOk()
            ->assertJsonPath('summary.tour_count', 0)
            ->assertJsonPath('pending_records.tours_pending_reconciliation', 1)
            ->assertJsonPath('pending_records.ready_import_rows_awaiting_confirmation', 1)
            ->assertJsonPath('pending_records.rows_needing_review', 1)
            ->assertJsonPath('pending_records.batches_awaiting_analysis', 0);
    }
}
