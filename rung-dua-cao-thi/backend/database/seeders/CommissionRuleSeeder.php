<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CommissionRuleSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $rules = [
            ['rule_key' => 'door_up_to_threshold', 'effective_from' => '2026-10-24', 'guest_threshold' => 10, 'amount' => 50000],
            ['rule_key' => 'door_above_threshold', 'effective_from' => '2026-10-24', 'guest_threshold' => 10, 'amount' => 100000],
            ['rule_key' => 'coconut_per_portion', 'effective_from' => '2026-10-24', 'guest_threshold' => null, 'amount' => 10000],
            ['rule_key' => 'ao_dai_per_unit', 'effective_from' => '2026-10-24', 'guest_threshold' => null, 'amount' => 20000],
            ['rule_key' => 'plastic_photo_per_unit', 'effective_from' => '2026-11-09', 'guest_threshold' => null, 'amount' => 10000],
        ];

        foreach ($rules as $rule) {
            DB::table('commission_rules')->insertOrIgnore([
                ...$rule,
                'effective_to' => null,
                'needs_reconciliation' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
