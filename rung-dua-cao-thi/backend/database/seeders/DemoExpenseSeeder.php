<?php

namespace Database\Seeders;

use App\Models\Expense;
use App\Models\TourGroup;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use RuntimeException;

class DemoExpenseSeeder extends Seeder
{
    public function run(): void
    {
        $actor = User::query()->where('email', 'manager@caothi.local')->first();
        if ($actor === null) {
            throw new RuntimeException('Hãy seed trước tài khoản manager@caothi.local để ghi nhận chi phí demo.');
        }

        for ($index = 1; $index <= 8; $index++) {
            $marker = sprintf('DEMO-SEED:EXPENSE-%02d', $index);
            if (Expense::withTrashed()->where('notes', $marker)->exists()) {
                continue;
            }

            $tourDate = Carbon::parse('2026-10-24')->addDays($index * 2)->toDateString();
            $tour = TourGroup::query()->where('notes', sprintf('DEMO-SEED:TOUR-%02d', $index * 3))->first();

            Expense::query()->create([
                'category' => 'Demo - Chi phí vận hành',
                'amount' => 35000 + ($index * 5000),
                'occurred_on' => $tourDate,
                'tour_group_id' => $tour?->id,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
                'notes' => $marker,
            ]);
        }
    }
}
