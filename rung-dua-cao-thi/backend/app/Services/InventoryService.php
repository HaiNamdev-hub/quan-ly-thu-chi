<?php

namespace App\Services;

use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\TourGroup;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryService
{
    /** @param array<string, mixed> $newCounts */
    public function reconcileTourPhotos(TourGroup $tour, array $newCounts, User $actor): void
    {
        foreach (['large_frame', 'small_frame', 'large_plastic', 'small_plastic'] as $sku) {
            $item = InventoryItem::query()->where('sku', $sku)->lockForUpdate()->first();
            if ($item === null) {
                continue;
            }

            $trackedQuantity = -(int) InventoryTransaction::query()
                ->where('tour_group_id', $tour->id)
                ->where('inventory_item_id', $item->id)
                ->sum('quantity_delta');
            $difference = (int) ($newCounts[$sku] ?? 0) - $trackedQuantity;
            if ($difference === 0) {
                continue;
            }

            if ($difference > 0 && $item->stock_control_enabled && $item->current_quantity < $difference) {
                throw ValidationException::withMessages([
                    'photos' => [sprintf('Không đủ tồn kho %s: còn %s %s.', $item->name, $item->current_quantity, $item->unit)],
                ]);
            }

            $item->increment('current_quantity', -$difference);
            InventoryTransaction::create([
                'inventory_item_id' => $item->id,
                'transaction_type' => $difference > 0 ? 'TOUR_SALE' : 'TOUR_RETURN',
                'quantity_delta' => -$difference,
                'tour_group_id' => $tour->id,
                'actor_id' => $actor->id,
                'notes' => 'Tự động theo cập nhật ảnh đoàn '.$tour->code,
            ]);
        }
    }

    public function reverseTourPhotos(TourGroup $tour, User $actor): void
    {
        $this->reconcileTourPhotos($tour, [], $actor);
    }

    public function recordMovement(InventoryItem $item, int $quantityDelta, string $type, ?string $notes, User $actor): InventoryTransaction
    {
        return DB::transaction(function () use ($item, $quantityDelta, $type, $notes, $actor): InventoryTransaction {
            $lockedItem = InventoryItem::query()->lockForUpdate()->findOrFail($item->id);
            if ($lockedItem->stock_control_enabled && $lockedItem->current_quantity + $quantityDelta < 0) {
                throw ValidationException::withMessages(['quantity_delta' => ['Thao tác này làm tồn kho âm.']]);
            }
            $lockedItem->increment('current_quantity', $quantityDelta);

            return InventoryTransaction::create([
                'inventory_item_id' => $lockedItem->id,
                'transaction_type' => $type,
                'quantity_delta' => $quantityDelta,
                'actor_id' => $actor->id,
                'notes' => $notes,
            ]);
        });
    }
}
