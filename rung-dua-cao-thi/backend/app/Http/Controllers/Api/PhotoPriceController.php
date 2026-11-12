<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PhotoPriceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate(['date' => ['sometimes', 'date']]);
        $date = $data['date'] ?? now()->toDateString();
        $prices = DB::table('photo_price_histories')->whereDate('effective_from', '<=', $date)
            ->where(fn ($query) => $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $date))
            ->orderBy('photo_type')->get();

        $counts = DB::table('tour_photos')->join('tour_groups', 'tour_groups.id', '=', 'tour_photos.tour_group_id')
            ->whereDate('tour_groups.tour_date', $date)->whereNull('tour_groups.deleted_at')
            ->where(function ($query): void {
                $query->whereNull('tour_groups.source_type')
                    ->orWhereNotIn('tour_groups.source_type', ['DEMO', 'demo']);
            })
            ->where(function ($query): void {
                $query->whereNull('tour_groups.notes')
                    ->orWhere('tour_groups.notes', 'not like', 'DEMO-SEED:%');
            })
            ->groupBy('tour_photos.photo_type')->select('tour_photos.photo_type', DB::raw('SUM(tour_photos.quantity) as quantity'))->pluck('quantity', 'photo_type');

        return response()->json([
            'date' => $date,
            'prices' => $prices->map(fn (object $price): array => [
                'photo_type' => $price->photo_type,
                'effective_from' => $price->effective_from,
                'vietnamese_price' => (int) $price->vietnamese_price,
                'international_price' => (int) $price->international_price,
                'unit_cost' => (int) $price->unit_cost,
                'quantity_sold' => (int) ($counts[$price->photo_type] ?? 0),
                'needs_reconciliation' => (bool) $price->needs_reconciliation,
            ]),
        ]);
    }

    public function store(Request $request, AuditLogger $auditLogger): JsonResponse
    {
        $data = $request->validate([
            'photo_type' => ['required', Rule::in(['large_frame', 'small_frame', 'large_plastic', 'small_plastic'])],
            'effective_from' => ['required', 'date'],
            'vietnamese_price' => ['required', 'integer', 'min:0'],
            'international_price' => ['required', 'integer', 'min:0'],
            'unit_cost' => ['required', 'integer', 'min:0'],
            'needs_reconciliation' => ['sometimes', 'boolean'],
        ]);

        return DB::transaction(function () use ($data, $auditLogger): JsonResponse {
            $effectiveFrom = Carbon::parse($data['effective_from'])->toDateString();
            $sameDate = DB::table('photo_price_histories')->where('photo_type', $data['photo_type'])
                ->whereDate('effective_from', $effectiveFrom)->exists();
            if ($sameDate) {
                abort(422, 'Đã có mức giá cho loại ảnh vào ngày hiệu lực này. Hãy chọn ngày mới để giữ nguyên lịch sử.');
            }

            $previous = DB::table('photo_price_histories')->where('photo_type', $data['photo_type'])
                ->whereDate('effective_from', '<', $effectiveFrom)->orderByDesc('effective_from')->first();
            if ($previous !== null && ($previous->effective_to === null || $previous->effective_to >= $effectiveFrom)) {
                DB::table('photo_price_histories')->where('id', $previous->id)
                    ->update(['effective_to' => Carbon::parse($effectiveFrom)->subDay()->toDateString(), 'updated_at' => now()]);
            }

            $next = DB::table('photo_price_histories')->where('photo_type', $data['photo_type'])
                ->whereDate('effective_from', '>', $effectiveFrom)->orderBy('effective_from')->first();
            $effectiveTo = $next !== null ? Carbon::parse($next->effective_from)->subDay()->toDateString() : null;
            $id = DB::table('photo_price_histories')->insertGetId([
                ...$data,
                'effective_from' => $effectiveFrom,
                'effective_to' => $effectiveTo,
                'needs_reconciliation' => $data['needs_reconciliation'] ?? true,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $price = DB::table('photo_price_histories')->where('id', $id)->first();
            $auditLogger->record('photo.price_changed', null, null, [
                'photo_type' => $price->photo_type, 'effective_from' => $price->effective_from,
                'vietnamese_price' => $price->vietnamese_price, 'international_price' => $price->international_price,
                'unit_cost' => $price->unit_cost, 'needs_reconciliation' => $price->needs_reconciliation,
            ]);

            return response()->json(['data' => $price], 201);
        });
    }

    public function history(Request $request): JsonResponse
    {
        abort_unless($request->user()->role->value === 'ADMIN', 403);
        $history = DB::table('photo_price_histories')->orderByDesc('effective_from')->orderBy('photo_type')->paginate(100);

        return response()->json($history);
    }
}
