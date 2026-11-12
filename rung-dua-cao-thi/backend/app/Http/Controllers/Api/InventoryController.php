<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Services\AuditLogger;
use App\Services\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InventoryController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => InventoryItem::query()->where('is_active', true)->orderBy('name')->get()]);
    }

    public function store(Request $request, AuditLogger $auditLogger): JsonResponse
    {
        $this->authorizeManager($request);
        $data = $request->validate([
            'sku' => ['required', 'string', 'max:60', 'regex:/^[A-Za-z0-9._-]+$/', 'unique:inventory_items,sku'],
            'name' => ['required', 'string', 'max:150'], 'unit' => ['required', 'string', 'max:30'],
            'opening_quantity' => ['sometimes', 'integer', 'min:0'], 'low_stock_threshold' => ['sometimes', 'integer', 'min:0'],
            'stock_control_enabled' => ['sometimes', 'boolean'],
        ]);
        $opening = (int) ($data['opening_quantity'] ?? 0);
        $item = InventoryItem::create([...$data, 'opening_quantity' => $opening, 'current_quantity' => $opening]);
        if ($opening > 0) {
            InventoryTransaction::create([
                'inventory_item_id' => $item->id, 'transaction_type' => 'OPENING',
                'quantity_delta' => $opening, 'actor_id' => $request->user()->id, 'notes' => 'Tồn đầu kỳ',
            ]);
        }
        $auditLogger->record('inventory_item.created', $item, null, $item->toArray());

        return response()->json(['data' => $item], 201);
    }

    public function update(Request $request, InventoryItem $inventoryItem, AuditLogger $auditLogger): JsonResponse
    {
        $this->authorizeManager($request);
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:150'], 'unit' => ['sometimes', 'required', 'string', 'max:30'],
            'low_stock_threshold' => ['sometimes', 'integer', 'min:0'],
            'stock_control_enabled' => ['sometimes', 'boolean'], 'is_active' => ['sometimes', 'boolean'],
        ]);
        if (($data['stock_control_enabled'] ?? false) && $inventoryItem->current_quantity < 0) {
            abort(422, 'Không thể bật kiểm soát khi tồn kho đang âm. Hãy nhập kho hoặc điều chỉnh tồn trước.');
        }
        $before = $inventoryItem->only(['name', 'unit', 'low_stock_threshold', 'stock_control_enabled', 'is_active']);
        $inventoryItem->update($data);
        $auditLogger->record('inventory_item.updated', $inventoryItem, $before, $inventoryItem->only(array_keys($before)));

        return response()->json(['data' => $inventoryItem->refresh()]);
    }

    public function transactions(Request $request): JsonResponse
    {
        $this->authorizeManager($request);
        $items = InventoryTransaction::query()->with(['item:id,sku,name,unit', 'actor:id,name', 'tour:id,code'])
            ->latest('id')->paginate(50);

        return response()->json($items);
    }

    public function storeTransaction(Request $request, InventoryService $inventoryService, AuditLogger $auditLogger): JsonResponse
    {
        $this->authorizeManager($request);
        $data = $request->validate([
            'inventory_item_id' => ['required', 'integer', 'exists:inventory_items,id'],
            'transaction_type' => ['required', Rule::in(['RECEIPT', 'ISSUE', 'ADJUSTMENT'])],
            'quantity_delta' => ['required', 'integer', 'not_in:0'],
            'notes' => ['required', 'string', 'min:3', 'max:2000'],
        ]);
        if (($data['transaction_type'] === 'RECEIPT' && $data['quantity_delta'] < 0)
            || ($data['transaction_type'] === 'ISSUE' && $data['quantity_delta'] > 0)) {
            abort(422, 'Số lượng nhập phải dương và số lượng xuất phải âm.');
        }
        $item = InventoryItem::query()->findOrFail($data['inventory_item_id']);
        $transaction = $inventoryService->recordMovement(
            $item,
            (int) $data['quantity_delta'],
            $data['transaction_type'],
            $data['notes'],
            $request->user(),
        );
        $auditLogger->record('inventory.transaction_recorded', $transaction, null, $transaction->toArray());

        return response()->json(['data' => $transaction->load('item:id,sku,name,unit')], 201);
    }

    private function authorizeManager(Request $request): void
    {
        abort_unless(in_array($request->user()->role->value, ['ADMIN', 'MANAGER'], true), 403);
    }
}
