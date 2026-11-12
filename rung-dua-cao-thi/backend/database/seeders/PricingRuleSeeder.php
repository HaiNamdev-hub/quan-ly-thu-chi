<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PricingRuleSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        DB::table('ticket_price_histories')->insertOrIgnore([
            ['customer_type' => 'VIETNAMESE', 'effective_from' => '2026-10-24', 'ticket_price' => 50000, 'ticket_cost' => 30000, 'needs_reconciliation' => false, 'created_at' => $now, 'updated_at' => $now],
            ['customer_type' => 'INTERNATIONAL', 'effective_from' => '2026-10-24', 'ticket_price' => 60000, 'ticket_cost' => 30000, 'needs_reconciliation' => false, 'created_at' => $now, 'updated_at' => $now],
        ]);

        $photoPrices = [
            ['photo_type' => 'large_frame', 'vietnamese_price' => 180000, 'international_price' => 200000, 'unit_cost' => 75000],
            ['photo_type' => 'small_frame', 'vietnamese_price' => 120000, 'international_price' => 150000, 'unit_cost' => 40000],
            ['photo_type' => 'large_plastic', 'vietnamese_price' => 100000, 'international_price' => 120000, 'unit_cost' => 10000],
            ['photo_type' => 'small_plastic', 'vietnamese_price' => 70000, 'international_price' => 100000, 'unit_cost' => 5000],
        ];

        foreach ($photoPrices as $price) {
            DB::table('photo_price_histories')->insertOrIgnore([
                ...$price,
                'effective_from' => '2026-11-09',
                'needs_reconciliation' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        DB::table('operational_rules')->insertOrIgnore([
            ['rule_key' => 'guests_per_boat', 'effective_from' => '2026-10-24', 'value' => 2, 'needs_reconciliation' => false, 'created_at' => $now, 'updated_at' => $now],
            ['rule_key' => 'boat_cost_per_boat', 'effective_from' => '2026-10-24', 'value' => 40000, 'needs_reconciliation' => false, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }
}
