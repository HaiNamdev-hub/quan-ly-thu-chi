<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Role;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function summary(Request $request, DashboardService $dashboard): JsonResponse
    {
        $validated = $request->validate([
            'mode' => ['sometimes', 'in:day,month,cumulative'],
            'date' => ['sometimes', 'date'],
        ]);
        $date = Carbon::parse($validated['date'] ?? now()->toDateString());

        if ($request->user()->role === Role::Staff) {
            return response()->json(['operations' => $dashboard->operations($request->user(), now())]);
        }

        return response()->json($dashboard->summary($validated['mode'] ?? 'day', $date));
    }

    public function cashReconciliation(Request $request, DashboardService $dashboard): JsonResponse
    {
        abort_unless($request->user()->role !== Role::Staff, 403);
        $validated = $request->validate([
            'mode' => ['sometimes', 'in:day,month,cumulative'],
            'date' => ['sometimes', 'date'],
        ]);
        $payload = $dashboard->summary(
            $validated['mode'] ?? 'day',
            Carbon::parse($validated['date'] ?? now()->toDateString()),
        );

        return response()->json($payload['cash_reconciliation']);
    }
}
