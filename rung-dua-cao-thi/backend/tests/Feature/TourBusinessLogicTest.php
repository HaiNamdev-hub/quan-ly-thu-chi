<?php

namespace Tests\Feature;

use App\Models\User;
use App\Role;
use Database\Seeders\BusinessRulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TourBusinessLogicTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BusinessRulesSeeder::class);
    }

    public function test_twenty_guests_calculate_ticket_and_boat_costs_on_backend(): void
    {
        $manager = User::factory()->create(['role' => Role::Manager]);

        $this->actingAs($manager)->postJson('/api/tours', $this->tourPayload())
            ->assertCreated()
            ->assertJsonPath('data.total_guests', 20)
            ->assertJsonPath('data.paid_tickets', 18)
            ->assertJsonPath('data.boats', 10)
            ->assertJsonPath('data.ticket_cost', 540000)
            ->assertJsonPath('data.boat_cost', 400000)
            ->assertJsonPath('data.code', 'CT-01-11/11');

        $this->assertDatabaseHas('tour_groups', ['ticket_payment_method' => null, 'ticket_revenue' => 1000000]);
    }

    public function test_unclassified_ticket_income_stays_unclassified_and_photo_prices_are_snapshotted(): void
    {
        $manager = User::factory()->create(['role' => Role::Manager]);
        $payload = $this->tourPayload([
            'tour_date' => '2026-11-09',
            'vietnamese_guests' => 0,
            'international_guests' => 1,
            'photos' => ['large_frame' => 1, 'small_plastic' => 2],
        ]);

        $response = $this->actingAs($manager)->postJson('/api/tours', $payload)->assertCreated();
        $tourId = $response->json('data.id');

        $this->assertDatabaseHas('tour_groups', ['id' => $tourId, 'ticket_payment_method' => null, 'ticket_revenue' => 60000, 'photo_revenue' => 400000, 'photo_cost' => 85000, 'photo_commission' => 20000]);
        $this->assertDatabaseHas('tour_photos', ['tour_group_id' => $tourId, 'photo_type' => 'large_frame', 'unit_price_snapshot' => 200000]);

        DB::table('photo_price_histories')->where('photo_type', 'large_frame')->update(['international_price' => 999999]);

        $this->assertDatabaseHas('tour_photos', ['tour_group_id' => $tourId, 'photo_type' => 'large_frame', 'unit_price_snapshot' => 200000]);
    }

    public function test_staff_cannot_edit_another_staff_members_tour(): void
    {
        $creator = User::factory()->create(['role' => Role::Staff]);
        $otherStaff = User::factory()->create(['role' => Role::Staff]);
        $created = $this->actingAs($creator)->postJson('/api/tours', $this->tourPayload())->assertCreated();
        $tourId = $created->json('data.id');

        $this->actingAs($otherStaff)->putJson('/api/tours/'.$tourId, [])->assertForbidden();
    }

    /** @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function tourPayload(array $overrides = []): array
    {
        return array_replace([
            'tour_date' => '2026-11-11',
            'vietnamese_guests' => 20,
            'international_guests' => 0,
            'ticket_payment_method' => null,
            'coconut_portions' => 5,
            'ao_dai_units' => 1,
            'ao_dai_revenue' => 50000,
        ], $overrides);
    }
}
