<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class BusinessRulesSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            PricingRuleSeeder::class,
            CommissionRuleSeeder::class,
        ]);
    }
}
