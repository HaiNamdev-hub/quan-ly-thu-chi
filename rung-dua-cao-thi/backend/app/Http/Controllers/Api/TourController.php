<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTourRequest;
use App\Http\Requests\UpdateTourRequest;
use App\Models\TourGroup;
use App\Role;
use App\Services\AuditLogger;
use App\Services\InventoryService;
use App\Services\TourService as TourBusinessService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TourController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', TourGroup::class);
        $user = $request->user();
        $date = $user->role === Role::Staff ? now()->toDateString() : $request->string('date', now()->toDateString())->toString();

        $tours = TourGroup::query()->excludeDemo()->with('guide:id,name,phone')
            ->whereDate('tour_date', $date)
            ->when($request->filled('guide_id'), fn ($query) => $query->where('guide_id', $request->integer('guide_id')))
            ->when($request->filled('payment_method'), fn ($query) => $query->where('ticket_payment_method', $request->string('payment_method')->toString()))
            ->orderBy('sequence')->get();

        return response()->json(['data' => $tours->map(fn (TourGroup $tour): array => $this->payload($tour, $user->role === Role::Staff))]);
    }

    public function store(StoreTourRequest $request, TourBusinessService $tourService, AuditLogger $auditLogger): JsonResponse
    {
        return DB::transaction(function () use ($request, $tourService, $auditLogger): JsonResponse {
            $tour = $tourService->create($request->validated(), $request->user());
            $auditLogger->record('tour.created', $tour, null, $tour->only(['code', 'tour_date', 'status', 'total_guests']));

            return response()->json(['data' => $this->payload($tour, $request->user()->role === Role::Staff)], 201);
        });
    }

    public function show(TourGroup $tour): JsonResponse
    {
        $this->authorize('view', $tour);
        $tour->load(['guide:id,name,phone', 'services', 'photos']);

        return response()->json(['data' => $this->payload($tour, request()->user()->role === Role::Staff)]);
    }

    public function update(UpdateTourRequest $request, TourGroup $tour, TourBusinessService $tourService, AuditLogger $auditLogger): JsonResponse
    {
        return DB::transaction(function () use ($request, $tour, $tourService, $auditLogger): JsonResponse {
            $before = $tour->only(['code', 'tour_date', 'guide_id', 'vietnamese_guests', 'international_guests', 'ticket_revenue', 'ticket_cost', 'status']);
            $tour = $tourService->update($tour, $request->validated(), $request->user());
            $auditLogger->record('tour.updated', $tour, $before, $tour->only(array_keys($before)));

            return response()->json(['data' => $this->payload($tour, $request->user()->role === Role::Staff)]);
        });
    }

    public function destroy(TourGroup $tour, Request $request, InventoryService $inventoryService, AuditLogger $auditLogger): JsonResponse
    {
        $this->authorize('delete', $tour);
        DB::transaction(function () use ($tour, $request, $inventoryService, $auditLogger): void {
            $before = $tour->only(['code', 'tour_date', 'status', 'ticket_revenue', 'ticket_cost']);
            $inventoryService->reverseTourPhotos($tour, $request->user());
            $tour->delete();
            $auditLogger->record('tour.deleted', $tour, $before);
        });

        return response()->json(['message' => 'Đã chuyển đoàn vào thùng lưu trữ.']);
    }

    public function submit(TourGroup $tour, AuditLogger $auditLogger): JsonResponse
    {
        abort_unless($tour->created_by === request()->user()->id || request()->user()->role !== Role::Staff, 403);
        abort_unless($tour->status === 'DRAFT', 422, 'Chỉ đoàn nháp mới được gửi duyệt.');
        $tour->update(['status' => 'PENDING', 'updated_by' => request()->user()->id]);
        $auditLogger->record('tour.submitted', $tour, ['status' => 'DRAFT'], ['status' => 'PENDING']);

        return response()->json(['data' => $this->payload($tour, request()->user()->role === Role::Staff)]);
    }

    public function approve(TourGroup $tour, AuditLogger $auditLogger): JsonResponse
    {
        abort_unless(in_array($tour->status, ['PENDING', 'DRAFT'], true), 422, 'Đoàn không ở trạng thái chờ duyệt.');
        $tour->update(['status' => 'APPROVED', 'approved_by' => request()->user()->id, 'updated_by' => request()->user()->id]);
        $auditLogger->record('tour.approved', $tour, ['status' => 'PENDING'], ['status' => 'APPROVED']);

        return response()->json(['data' => $this->payload($tour)]);
    }

    public function lock(TourGroup $tour, AuditLogger $auditLogger): JsonResponse
    {
        abort_unless($tour->status === 'APPROVED', 422, 'Cần duyệt đoàn trước khi chốt.');
        $tour->update(['status' => 'LOCKED', 'locked_by' => request()->user()->id, 'updated_by' => request()->user()->id]);
        $auditLogger->record('tour.locked', $tour, ['status' => 'APPROVED'], ['status' => 'LOCKED']);

        return response()->json(['data' => $this->payload($tour)]);
    }

    public function unlock(Request $request, TourGroup $tour, AuditLogger $auditLogger): JsonResponse
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:2000']]);
        abort_unless($tour->status === 'LOCKED', 422, 'Đoàn chưa được chốt.');
        $before = $tour->only(['status', 'ticket_revenue', 'ticket_cost', 'photo_revenue']);
        $tour->update(['status' => 'APPROVED', 'unlock_reason' => $validated['reason'], 'locked_by' => null, 'updated_by' => $request->user()->id]);
        $auditLogger->record('tour.unlocked', $tour, $before, [...$tour->only(array_keys($before)), 'reason' => $validated['reason']]);

        return response()->json(['data' => $this->payload($tour)]);
    }

    /** @return array<string, mixed> */
    private function payload(TourGroup $tour, bool $hideFinancials = false): array
    {
        $tour->loadMissing(['guide:id,name,phone', 'services', 'photos']);
        if ($hideFinancials) {
            $tour->makeHidden([
                'ticket_vietnamese_unit_price', 'ticket_international_unit_price', 'ticket_cost_unit_price', 'boat_cost_unit_price',
                'ticket_revenue', 'ticket_cost', 'boat_cost', 'door_commission', 'coconut_commission_unit_price',
                'ao_dai_commission_unit_price', 'ao_dai_revenue', 'photo_revenue', 'photo_cost', 'photo_commission',
                'other_expenses', 'unlock_reason',
            ]);
            $tour->services->each(fn ($service) => $service->makeHidden(['revenue', 'commission_unit_price', 'commission_amount']));
            $tour->photos->each(fn ($photo) => $photo->makeHidden(['unit_price_snapshot', 'unit_cost_snapshot', 'revenue', 'cost', 'commission']));
        }

        return $tour->toArray();
    }
}
