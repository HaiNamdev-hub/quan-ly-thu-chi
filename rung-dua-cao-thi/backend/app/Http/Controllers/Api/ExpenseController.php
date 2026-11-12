<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\TourGroup;
use App\Role;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ExpenseController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'from' => ['sometimes', 'date'], 'to' => ['sometimes', 'date'],
            'category' => ['sometimes', 'string', 'max:80'], 'tour_group_id' => ['sometimes', 'integer'],
        ]);
        $expenses = Expense::query()->excludeDemo()->with('tour:id,code')->with('creator:id,name')
            ->when(isset($filters['from']), fn ($query) => $query->whereDate('occurred_on', '>=', $filters['from']))
            ->when(isset($filters['to']), fn ($query) => $query->whereDate('occurred_on', '<=', $filters['to']))
            ->when(isset($filters['category']), fn ($query) => $query->where('category', $filters['category']))
            ->when(isset($filters['tour_group_id']), fn ($query) => $query->where('tour_group_id', $filters['tour_group_id']))
            ->orderByDesc('occurred_on')->orderByDesc('id')->paginate(50);

        return response()->json($expenses);
    }

    public function store(Request $request, AuditLogger $auditLogger): JsonResponse
    {
        $data = $request->validate([
            'category' => ['required', 'string', 'max:80'], 'amount' => ['required', 'integer', 'min:1'],
            'occurred_on' => ['required', 'date'], 'tour_group_id' => ['nullable', 'integer', 'exists:tour_groups,id'],
            'notes' => ['nullable', 'string', 'max:5000'], 'reason' => ['nullable', 'string', 'min:5', 'max:2000'],
        ]);
        $this->rejectExcludedCategory($data['category']);
        $reason = $data['reason'] ?? null;
        $this->ensureRelatedTourIsEditable($request, $data['tour_group_id'] ?? null, $reason);
        unset($data['reason']);
        $expense = Expense::create([...$data, 'created_by' => $request->user()->id]);
        $auditLogger->record('expense.created', $expense, null, [...$expense->only(['category', 'amount', 'occurred_on', 'tour_group_id', 'notes']), 'reason' => $reason]);

        return response()->json(['data' => $expense], 201);
    }

    public function update(Request $request, Expense $expense, AuditLogger $auditLogger): JsonResponse
    {
        $data = $request->validate([
            'category' => ['sometimes', 'required', 'string', 'max:80'], 'amount' => ['sometimes', 'required', 'integer', 'min:1'],
            'occurred_on' => ['sometimes', 'required', 'date'], 'tour_group_id' => ['sometimes', 'nullable', 'integer', 'exists:tour_groups,id'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'], 'reason' => ['sometimes', 'nullable', 'string', 'min:5', 'max:2000'],
        ]);
        if (isset($data['category'])) {
            $this->rejectExcludedCategory($data['category']);
        }
        $this->ensureHistoricalReason($request, $expense, $data);
        unset($data['reason']);
        $before = $expense->only(['category', 'amount', 'occurred_on', 'tour_group_id', 'notes']);
        $expense->update([...$data, 'updated_by' => $request->user()->id]);
        $auditLogger->record('expense.updated', $expense, $before, [...$expense->only(array_keys($before)), 'reason' => $request->input('reason')]);

        return response()->json(['data' => $expense->refresh()]);
    }

    public function destroy(Request $request, Expense $expense, AuditLogger $auditLogger): JsonResponse
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'min:5', 'max:2000']]);
        $this->ensureHistoricalReason($request, $expense, $data);
        $before = $expense->only(['category', 'amount', 'occurred_on', 'tour_group_id', 'notes']);
        $expense->update(['deleted_by' => $request->user()->id]);
        $expense->delete();
        $auditLogger->record('expense.deleted', $expense, $before, ['reason' => $request->input('reason')]);

        return response()->json(['message' => 'Đã chuyển khoản chi vào thùng lưu trữ.']);
    }

    /** @param array<string, mixed> $data */
    private function ensureHistoricalReason(Request $request, Expense $expense, array $data): void
    {
        $targetTourId = $data['tour_group_id'] ?? $expense->tour_group_id;
        $this->ensureRelatedTourIsEditable($request, $expense->tour_group_id, $data['reason'] ?? null);
        if ($targetTourId !== $expense->tour_group_id) {
            $this->ensureRelatedTourIsEditable($request, $targetTourId, $data['reason'] ?? null);
        }
    }

    private function ensureRelatedTourIsEditable(Request $request, ?int $tourId, ?string $reason): void
    {
        $tour = $tourId !== null ? TourGroup::query()->find($tourId) : null;
        if ($tour?->status === 'LOCKED' && $request->user()->role === Role::Manager) {
            abort(403, 'Quản lý không được thay đổi khoản chi thuộc đoàn đã chốt.');
        }
        if ($tour?->status === 'LOCKED' && $request->user()->role === Role::Admin && empty($reason)) {
            abort(422, 'Cần ghi rõ lý do điều chỉnh khoản chi đã khóa.');
        }
    }

    private function rejectExcludedCategory(string $category): void
    {
        $normalized = Str::lower(Str::ascii($category));
        if (str_contains($normalized, 'nuoc uong khac') || str_contains($normalized, 'dua trai')) {
            abort(422, 'Danh mục nước uống khác và dừa trái không thuộc nghiệp vụ thống kê mới.');
        }
    }
}
