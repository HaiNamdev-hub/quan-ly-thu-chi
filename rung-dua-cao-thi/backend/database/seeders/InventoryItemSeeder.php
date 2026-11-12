<?php

namespace Database\Seeders;

use App\Models\InventoryItem;
use Illuminate\Database\Seeder;

class InventoryItemSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            'large_frame' => ['Khung ảnh lớn', 5],
            'small_frame' => ['Khung ảnh nhỏ', 5],
            'large_plastic' => ['Ảnh plastic lớn', 10],
            'small_plastic' => ['Ảnh plastic nhỏ', 10],
        ];

        foreach ($items as $sku => [$name, $lowStockThreshold]) {
            InventoryItem::query()->firstOrCreate(
                ['sku' => $sku],
                [
                    'name' => $name,
                    'unit' => 'tấm',
                    'opening_quantity' => 0,
                    'current_quantity' => 0,
                    'low_stock_threshold' => $lowStockThreshold,
                    'stock_control_enabled' => true,
                    'is_active' => true,
                ],
            );
        }
    }
}
