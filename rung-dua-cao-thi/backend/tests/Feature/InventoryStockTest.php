<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\User;
use App\Role;
use App\Services\TourService;
use Database\Seeders\BusinessRulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class InventoryStockTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BusinessRulesSeeder::class);
    }

    public function test_tour_photo_sales_and_edits_update_stock_inside_the_tour_transaction(): void
    {
        $manager = User::factory()->create(['role' => Role::Manager]);
        $item = InventoryItem::create([
            'sku' => 'large_frame', 'name' => 'Khung lớn', 'unit' => 'cái', 'opening_quantity' => 10,
            'current_quantity' => 10, 'stock_control_enabled' => true,
        ]);
        $service = app(TourService::class);
        $tour = $service->create($this->payload(3), $manager);

        $this->assertSame(7, $item->fresh()->current_quantity);
        $this->assertSame(-3, (int) InventoryTransaction::query()->where('tour_group_id', $tour->id)->sum('quantity_delta'));

        $tour = $service->update($tour, $this->payload(4), $manager);

        $this->assertSame(6, $item->fresh()->current_quantity);
        $this->assertSame(-4, (int) InventoryTransaction::query()->where('tour_group_id', $tour->id)->sum('quantity_delta'));
    }

    public function test_controlled_inventory_blocks_tour_sale_when_quantity_is_insufficient(): void
    {
        $manager = User::factory()->create(['role' => Role::Manager]);
        InventoryItem::create([
            'sku' => 'small_plastic', 'name' => 'Plastic nhỏ', 'unit' => 'tấm', 'opening_quantity' => 1,
            'current_quantity' => 1, 'stock_control_enabled' => true,
        ]);

        try {
            app(TourService::class)->create($this->payload(2, 'small_plastic'), $manager);
            $this->fail('Expected stock control to reject this tour sale.');
        } catch (ValidationException $exception) {
            $this->assertSame('Không đủ tồn kho Plastic nhỏ: còn 1 tấm.', $exception->errors()['photos'][0]);
        }

        $this->assertDatabaseCount('tour_groups', 0);
    }

    /** @return array<string, mixed> */
    private function payload(int $quantity, string $type = 'large_frame'): array
    {
        return [
            'tour_date' => '2026-11-11', 'vietnamese_guests' => 2, 'international_guests' => 0,
            'ticket_payment_method' => null, 'photos' => [$type => $quantity],
        ];
    }
}
