<?php

namespace App\Services;

use App\Models\ExcelImportBatch;
use App\Models\ExcelImportRow;
use App\Models\Expense;
use App\Models\TourGroup;
use App\Models\User;
use Carbon\CarbonPeriod;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

class DashboardService
{
    /** @return array<string, mixed> */
    public function summary(string $mode, Carbon $reportDate): array
    {
        [$from, $to] = match ($mode) {
            'month' => [$reportDate->copy()->startOfMonth(), $reportDate->copy()->endOfDay()],
            'cumulative' => [Carbon::parse('2026-10-24')->startOfDay(), $reportDate->copy()->endOfDay()],
            default => [$reportDate->copy()->startOfDay(), $reportDate->copy()->endOfDay()],
        };
        $tours = TourGroup::query()->excludeDemo()->whereDate('tour_date', '>=', $from->toDateString())
            ->whereDate('tour_date', '<=', $to->toDateString())
            ->where('counts_in_dashboard', true)
            ->whereIn('status', ['APPROVED', 'LOCKED'])
            ->with(['services:id,tour_group_id,commission_amount', 'photos:id,tour_group_id,quantity,revenue,cost,commission'])
            ->get();

        $daily = [];
        $summary = $this->emptyTotals();
        foreach ($tours as $tour) {
            $photoCommission = (int) $tour->photo_commission;
            $serviceCommission = (int) $tour->services->sum('commission_amount');
            $income = (int) $tour->ticket_revenue + (int) $tour->photo_revenue + (int) $tour->ao_dai_revenue;
            $expenses = (int) $tour->ticket_cost + (int) $tour->boat_cost + (int) $tour->door_commission
                + $serviceCommission + $photoCommission + (int) $tour->other_expenses;
            $photoCost = (int) $tour->photo_cost;
            $summary['ticket_revenue'] += (int) $tour->ticket_revenue;
            $summary['photo_revenue'] += (int) $tour->photo_revenue;
            $summary['ao_dai_revenue'] += (int) $tour->ao_dai_revenue;
            $summary['ticket_cost'] += (int) $tour->ticket_cost;
            $summary['boat_cost'] += (int) $tour->boat_cost;
            $summary['commission'] += (int) $tour->door_commission + $serviceCommission + $photoCommission;
            $summary['other_expenses'] += (int) $tour->other_expenses;
            $summary['photo_cost'] += $photoCost;
            $summary['tour_count']++;
            $summary['total_guests'] += (int) $tour->total_guests;

            $key = $tour->tour_date->toDateString();
            $daily[$key] ??= ['date' => $key, 'revenue' => 0, 'operating_expenses' => 0, 'photo_cost' => 0];
            $daily[$key]['revenue'] += $income;
            $daily[$key]['operating_expenses'] += $expenses;
            $daily[$key]['photo_cost'] += $photoCost;

            if ($tour->ticket_payment_method === 'cash') {
                $summary['ticket_cash'] += (int) $tour->ticket_revenue;
            } elseif ($tour->ticket_payment_method === 'company_transfer') {
                $summary['ticket_company_transfer'] += (int) $tour->ticket_revenue;
            } elseif ($tour->ticket_payment_method === 'personal_transfer') {
                $summary['ticket_personal_transfer'] += (int) $tour->ticket_revenue;
            } else {
                $summary['ticket_unclassified'] += (int) $tour->ticket_revenue;
            }
        }

        $expensesByDate = Expense::query()->excludeDemo()->whereDate('occurred_on', '>=', $from->toDateString())
            ->whereDate('occurred_on', '<=', $to->toDateString())
            ->get(['occurred_on', 'amount'])->groupBy(fn (Expense $expense): string => $expense->occurred_on->toDateString());
        foreach ($expensesByDate as $date => $expenses) {
            $amount = (int) $expenses->sum('amount');
            $summary['other_expenses'] += $amount;
            $daily[$date] ??= ['date' => $date, 'revenue' => 0, 'operating_expenses' => 0, 'photo_cost' => 0];
            $daily[$date]['operating_expenses'] += $amount;
        }

        $summary['revenue'] = $summary['ticket_revenue'] + $summary['photo_revenue'] + $summary['ao_dai_revenue'];
        $summary['operating_expenses'] = $summary['ticket_cost'] + $summary['boat_cost'] + $summary['commission'] + $summary['other_expenses'];
        $summary['net_before_photo_cost'] = $summary['revenue'] - $summary['operating_expenses'];
        $summary['profit_after_photo_cost'] = $summary['net_before_photo_cost'] - $summary['photo_cost'];
        $summary['cash_expected'] = $summary['ticket_cash'] + $summary['photo_revenue'] + $summary['ao_dai_revenue'] - $summary['operating_expenses'];
        $summary['company_transfer'] = $summary['ticket_company_transfer'];
        $summary['personal_transfer'] = $summary['ticket_personal_transfer'];
        $summary['reconciliation_total'] = $summary['ticket_cash'] + $summary['company_transfer']
            + $summary['personal_transfer'] + $summary['ticket_unclassified'] + $summary['photo_revenue'] + $summary['ao_dai_revenue'];
        $summary['reconciliation_variance'] = $summary['revenue'] - $summary['reconciliation_total'];

        $chart = [];
        foreach (CarbonPeriod::create($from->copy()->startOfDay(), $to->copy()->startOfDay()) as $day) {
            $key = $day->toDateString();
            $item = $daily[$key] ?? ['date' => $key, 'revenue' => 0, 'operating_expenses' => 0, 'photo_cost' => 0];
            $item['profit'] = $item['revenue'] - $item['operating_expenses'] - $item['photo_cost'];
            $chart[] = $item;
        }

        return [
            'period' => ['mode' => $mode, 'from' => $from->toDateString(), 'to' => $to->toDateString()],
            'summary' => $summary,
            'pending_records' => $this->pendingRecords(),
            'daily' => $chart,
            'cash_reconciliation' => [
                'expected_cash' => $summary['cash_expected'],
                'company_transfer' => $summary['company_transfer'],
                'personal_transfer' => $summary['personal_transfer'],
                'unclassified_tickets' => $summary['ticket_unclassified'],
                'reconciliation_total' => $summary['reconciliation_total'],
                'variance' => $summary['reconciliation_variance'],
                'is_actual_balance' => false,
            ],
        ];
    }

    /** @return array{tours_pending_reconciliation: int, ready_import_rows_awaiting_confirmation: int, rows_needing_review: int, batches_awaiting_analysis: int} */
    private function pendingRecords(): array
    {
        $pending = [
            'tours_pending_reconciliation' => TourGroup::query()->excludeDemo()->where('is_reconciled', false)->count(),
            'ready_import_rows_awaiting_confirmation' => 0,
            'rows_needing_review' => 0,
            'batches_awaiting_analysis' => 0,
        ];

        if (! Schema::hasTable('excel_import_rows') || ! Schema::hasTable('excel_import_batches')) {
            return $pending;
        }

        $pending['ready_import_rows_awaiting_confirmation'] = ExcelImportRow::query()
            ->where('status', 'ready')
            ->whereHas('batch', fn ($query) => $query->where('status', 'analyzed'))
            ->count();
        $pending['rows_needing_review'] = ExcelImportRow::query()->whereIn('status', ['needs_review', 'conflict'])->count();
        $pending['batches_awaiting_analysis'] = ExcelImportBatch::query()->where('status', 'uploaded')->count();

        return $pending;
    }

    /** @return array<string, int> */
    private function emptyTotals(): array
    {
        return array_fill_keys([
            'ticket_revenue', 'photo_revenue', 'ao_dai_revenue', 'ticket_cost', 'boat_cost', 'commission',
            'other_expenses', 'photo_cost', 'tour_count', 'total_guests', 'ticket_cash', 'ticket_company_transfer',
            'ticket_personal_transfer', 'ticket_unclassified', 'revenue', 'operating_expenses', 'net_before_photo_cost',
            'profit_after_photo_cost', 'cash_expected', 'company_transfer', 'personal_transfer', 'reconciliation_total',
            'reconciliation_variance',
        ], 0);
    }

    /** @return array<string, int> */
    public function operations(User $user, Carbon $date): array
    {
        $tours = TourGroup::query()->excludeDemo()->whereDate('tour_date', $date->toDateString())
            ->where('counts_in_dashboard', true)->with('photos')->get();

        return [
            'date' => $date->toDateString(),
            'tour_count' => $tours->count(),
            'total_guests' => (int) $tours->sum('total_guests'),
            'photos_sold' => (int) $tours->sum(fn (TourGroup $tour): int => (int) $tour->photos->sum('quantity')),
            'incomplete_tours' => $tours->whereIn('status', ['DRAFT', 'PENDING'])->count(),
            'my_tours' => $tours->where('created_by', $user->id)->count(),
        ];
    }
}
