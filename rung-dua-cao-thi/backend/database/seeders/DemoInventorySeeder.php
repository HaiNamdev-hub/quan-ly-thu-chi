<?php

namespace Database\Seeders;

use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Database\Seeder;
use RuntimeException;

class DemoInventorySeeder extends Seeder
{
    public function run(): void
    {
        $actor = User::query()->where('email', 'manager@caothi.local')->first();
        if ($actor === null) {
            throw new RuntimeException('Hãy seed trước tài khoản manager@caothi.local để ghi nhận tồn kho demo.');
        }

        $inventoryService = app(InventoryService::class);
        $stockBySku = [
            'large_frame' => 30,
            'small_frame' => 30,
            'large_plastic' => 40,
            'small_plastic' => 40,
        ];

        foreach ($stockBySku as $sku => $quantity) {
            $item = InventoryItem::query()->where('sku', $sku)->firstOrFail();
            $marker = 'DEMO-SEED:STOCK-IN:'.$sku;

            if (! InventoryTransaction::query()->where('notes', $marker)->exists()) {
                $inventoryService->recordMovement($item, $quantity, 'PURCHASE', $marker, $actor);
            }
        }
    }
}
