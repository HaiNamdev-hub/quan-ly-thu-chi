<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\Guide;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\TourGroup;
use App\Models\TourPhoto;
use App\Models\TourService;
use App\Models\User;
use App\Role;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_idempotent_demo_data_without_exposing_it_to_business_dashboard(): void
    {
        $manager = User::factory()->create(['email' => 'manager@caothi.local', 'role' => Role::Manager]);
        User::factory()->create(['email' => 'staff@caothi.local', 'role' => Role::Staff]);
        User::factory()->create(['email' => 'admin@caothi.local', 'role' => Role::Admin]);

        $this->seed(DemoSeeder::class);

        $this->assertSame(15, Guide::query()->where('notes', 'like', 'DEMO-SEED:GUIDE-%')->count());
        $this->assertSame(30, TourGroup::query()->where('notes', 'like', 'DEMO-SEED:TOUR-%')->count());
        $dailyTourCounts = TourGroup::query()->where('notes', 'like', 'DEMO-SEED:TOUR-%')->get(['tour_date'])
            ->countBy(fn (TourGroup $tour): string => $tour->tour_date->toDateString());
        $this->assertLessThanOrEqual(20, $dailyTourCounts->max());
        $this->assertSame(8, Expense::query()->where('notes', 'like', 'DEMO-SEED:EXPENSE-%')->count());
        $this->assertSame(4, InventoryItem::query()->count());
        $this->assertSame(4, InventoryTransaction::query()->where('notes', 'like', 'DEMO-SEED:STOCK-IN:%')->count());
        $this->assertDatabaseMissing('photo_price_histories', ['effective_from' => '2026-11-08']);
        $this->assertDatabaseHas('commission_rules', [
            'rule_key' => 'plastic_photo_per_unit',
            'effective_from' => '2026-11-09',
            'amount' => 10000,
            'needs_reconciliation' => true,
        ]);

        $twentyGuestTour = TourGroup::query()->where('notes', 'DEMO-SEED:TOUR-01')->firstOrFail();
        $this->assertSame('DEMO', $twentyGuestTour->source_type);
        $this->assertFalse($twentyGuestTour->counts_in_dashboard);
        $this->assertSame(20, $twentyGuestTour->total_guests);
        $this->assertSame(540000, $twentyGuestTour->ticket_cost);
        $this->assertSame(400000, $twentyGuestTour->boat_cost);
        $this->assertSame('cash', $twentyGuestTour->ticket_payment_method);
        $this->assertSame(50000, TourService::query()->where('tour_group_id', $twentyGuestTour->id)->where('service_type', 'coconut')->value('commission_amount'));
        $this->assertSame(0, TourService::query()->where('tour_group_id', $twentyGuestTour->id)->where('service_type', 'coconut')->value('revenue'));

        $companyTransferTour = TourGroup::query()->where('notes', 'DEMO-SEED:TOUR-02')->firstOrFail();
        $unclassifiedTour = TourGroup::query()->where('notes', 'DEMO-SEED:TOUR-04')->firstOrFail();
        $this->assertSame('company_transfer', $companyTransferTour->ticket_payment_method);
        $this->assertNull($unclassifiedTour->ticket_payment_method);

        $newPriceTourIds = TourGroup::query()->whereDate('tour_date', '>=', '2026-11-09')->pluck('id');
        $newPricePhoto = TourPhoto::query()->whereIn('tour_group_id', $newPriceTourIds)->first();
        $this->assertNotNull($newPricePhoto);
        $this->assertSame(120_000, (int) TourPhoto::query()->where('photo_type', 'large_plastic')->whereIn('tour_group_id', $newPriceTourIds)->value('unit_price_snapshot'));
        $this->assertSame(10_000, (int) TourPhoto::query()->where('photo_type', 'large_plastic')->whereIn('tour_group_id', $newPriceTourIds)->value('commission'));
        $this->assertSame(3, TourGroup::query()->where('notes', 'like', 'DEMO-SEED:TOUR-%')->whereDate('tour_date', '>=', '2026-11-09')->whereHas('photos')->count());
        $this->assertGreaterThan(0, InventoryTransaction::query()->where('transaction_type', 'TOUR_SALE')->where('notes', 'like', 'DEMO-SEED:TOUR-%')->count());

        $this->actingAs($manager)->getJson('/api/dashboard/summary?mode=day&date=2026-10-24')
            ->assertOk()
            ->assertJsonPath('summary.tour_count', 0)
            ->assertJsonPath('summary.ticket_cash', 0)
            ->assertJsonPath('summary.ticket_company_transfer', 0)
            ->assertJsonPath('pending_records.tours_pending_reconciliation', 0);

        $photoPrices = $this->actingAs($manager)->getJson('/api/photos?date=2026-11-10')->assertOk();
        foreach ($photoPrices->json('prices') as $price) {
            $this->assertSame(0, $price['quantity_sold']);
        }

        $this->seed(DemoSeeder::class);

        $this->assertSame(15, Guide::query()->where('notes', 'like', 'DEMO-SEED:GUIDE-%')->count());
        $this->assertSame(30, TourGroup::query()->where('notes', 'like', 'DEMO-SEED:TOUR-%')->count());
        $this->assertSame(8, Expense::query()->where('notes', 'like', 'DEMO-SEED:EXPENSE-%')->count());
    }

    public function test_it_refuses_demo_seeding_outside_local_and_testing_environments(): void
    {
        $this->app['env'] = 'production';

        try {
            app(DemoSeeder::class)->run();
            $this->fail('Seeder phải chặn môi trường production.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('DemoSeeder chỉ được chạy trong môi trường local hoặc testing.', $exception->getMessage());
        }

        $this->assertDatabaseCount('tour_groups', 0);
    }
}
