<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Guide;
use App\Models\TourGroup;
use App\Role;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GuideController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $guides = Guide::query()
            ->when($request->string('search')->isNotEmpty(), function ($query) use ($request): void {
                $term = '%'.$request->string('search')->toString().'%';
                $query->where(fn ($query) => $query->where('name', 'like', $term)->orWhere('phone', 'like', $term));
            })
            ->where('is_active', true)
            ->orderBy('name');

        if ($request->user()->role !== Role::Staff) {
            $guides
                ->withCount(['tours as tours_count' => fn ($query) => $query->excludeDemo()])
                ->withSum(['tours as tours_sum_total_guests' => fn ($query) => $query->excludeDemo()], 'total_guests')
                ->withSum(['tours as tours_sum_ticket_revenue' => fn ($query) => $query->excludeDemo()], 'ticket_revenue');
        }

        return response()->json($guides->paginate(30));
    }

    public function store(Request $request, AuditLogger $auditLogger): JsonResponse
    {
        abort_unless(in_array($request->user()->role, [Role::Admin, Role::Manager], true), 403);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        return DB::transaction(function () use ($validated, $auditLogger): JsonResponse {
            $guide = Guide::create($validated);
            $auditLogger->record('guide.created', $guide, null, $guide->only(['name', 'phone', 'notes']));

            return response()->json(['data' => $guide], 201);
        });
    }

    public function update(Request $request, Guide $guide, AuditLogger $auditLogger): JsonResponse
    {
        abort_unless(in_array($request->user()->role, [Role::Admin, Role::Manager], true), 403);
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        return DB::transaction(function () use ($guide, $validated, $auditLogger): JsonResponse {
            $before = $guide->only(['name', 'phone', 'notes', 'is_active']);
            $guide->update($validated);
            $auditLogger->record('guide.updated', $guide, $before, $guide->only(array_keys($before)));

            return response()->json(['data' => $guide->refresh()]);
        });
    }

    public function history(Request $request, Guide $guide): JsonResponse
    {
        abort_unless($request->user()->role !== Role::Staff, 403);

        return response()->json([
            'data' => TourGroup::query()->excludeDemo()->where('guide_id', $guide->id)->with('photos', 'services')
                ->orderByDesc('tour_date')->orderByDesc('sequence')->paginate(30),
        ]);
    }
}
