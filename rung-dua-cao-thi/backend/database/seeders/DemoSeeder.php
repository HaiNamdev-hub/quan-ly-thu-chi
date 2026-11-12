<?php

namespace Database\Seeders;

use App\Models\Expense;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\TourGroup;
use Illuminate\Database\Seeder;
use RuntimeException;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('DemoSeeder chỉ được chạy trong môi trường local hoặc testing.');
        }

        $this->assertDemoPeriodHasNoBusinessData();
        $this->assertPhotoInventoryIsIsolated();

        $this->call([
            BusinessRulesSeeder::class,
            InventoryItemSeeder::class,
            UserSeeder::class,
            DemoGuideSeeder::class,
            DemoInventorySeeder::class,
            DemoTourSeeder::class,
            DemoExpenseSeeder::class,
        ]);
    }

    private function assertDemoPeriodHasNoBusinessData(): void
    {
        $tourExists = TourGroup::withTrashed()->whereBetween('tour_date', ['2026-10-24', '2026-11-11'])
            ->where(fn ($query) => $query->whereNull('notes')->orWhere('notes', 'not like', 'DEMO-SEED:%'))
            ->exists();

        $expenseExists = Expense::withTrashed()->whereBetween('occurred_on', ['2026-10-24', '2026-11-11'])
            ->where(fn ($query) => $query->whereNull('notes')->orWhere('notes', 'not like', 'DEMO-SEED:%'))
            ->exists();

        if ($tourExists || $expenseExists) {
            throw new RuntimeException('DemoSeeder bị dừng vì khoảng ngày demo đã có đoàn hoặc khoản chi không mang nhãn DEMO-SEED. Hãy dùng database local/testing riêng.');
        }
    }

    private function assertPhotoInventoryIsIsolated(): void
    {
        $items = InventoryItem::query()->whereIn('sku', ['large_frame', 'small_frame', 'large_plastic', 'small_plastic'])->get();

        foreach ($items as $item) {
            $transactions = InventoryTransaction::query()->where('inventory_item_id', $item->id)->get();
            $hasBusinessTransaction = $transactions->contains(fn (InventoryTransaction $transaction): bool => ! str_starts_with((string) $transaction->notes, 'DEMO-SEED:'));
            $expectedQuantity = (int) $item->opening_quantity + (int) $transactions->sum('quantity_delta');

            if ($hasBusinessTransaction || (int) $item->opening_quantity !== 0 || (int) $item->current_quantity !== $expectedQuantity) {
                throw new RuntimeException('DemoSeeder bị dừng vì tồn kho ảnh đang chứa dữ liệu không phải demo. Hãy dùng database local/testing riêng.');
            }
        }
    }
}
