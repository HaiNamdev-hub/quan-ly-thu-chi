<?php

namespace Database\Seeders;

use App\Models\Guide;
use App\Models\InventoryTransaction;
use App\Models\TourGroup;
use App\Models\User;
use App\Services\TourService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DemoTourSeeder extends Seeder
{
    public function run(): void
    {
        $actor = User::query()->where('email', 'manager@caothi.local')->first();
        if ($actor === null) {
            throw new RuntimeException('Hãy seed trước tài khoản manager@caothi.local để tạo đoàn demo.');
        }

        $guides = Guide::query()->where('notes', 'like', 'DEMO-SEED:GUIDE-%')->orderBy('id')->get();
        if ($guides->count() !== 15) {
            throw new RuntimeException('Hãy tạo đủ 15 hướng dẫn viên demo trước khi tạo đoàn.');
        }

        $tourService = app(TourService::class);
        $paymentMethods = ['cash', 'company_transfer', 'personal_transfer', null];

        for ($index = 1; $index <= 30; $index++) {
            $marker = sprintf('DEMO-SEED:TOUR-%02d', $index);
            if (TourGroup::withTrashed()->where('notes', $marker)->exists()) {
                continue;
            }

            $dayOffset = $index <= 22 ? intdiv($index - 1, 2) : $index - 12;
            $tourDate = Carbon::parse('2026-10-24')->addDays($dayOffset)->toDateString();
            $sequence = (int) (TourGroup::withTrashed()->whereDate('tour_date', $tourDate)->max('sequence') ?? 0);
            $counter = DB::table('tour_counters')->where('tour_date', $tourDate)->value('last_sequence');

            if ($sequence > (int) ($counter ?? 0)) {
                DB::table('tour_counters')->updateOrInsert(
                    ['tour_date' => $tourDate],
                    ['last_sequence' => $sequence, 'created_at' => now(), 'updated_at' => now()],
                );
            }

            [$vietnameseGuests, $internationalGuests] = match ($index % 3) {
                1 => [12, 8],
                2 => [9, 0],
                default => [0, 14],
            };
            $coconutPortions = $index === 1 ? 5 : $index % 4;
            $photos = [];
            if ($tourDate >= '2026-11-09') {
                $photos = [
                    'large_frame' => $index % 2,
                    'small_frame' => 1,
                    'large_plastic' => 1,
                    'small_plastic' => $index % 2,
                ];
            }

            DB::transaction(function () use ($actor, $coconutPortions, $guides, $index, $internationalGuests, $marker, $paymentMethods, $photos, $tourDate, $tourService, $vietnameseGuests): void {
                $tour = $tourService->create([
                    'tour_date' => $tourDate,
                    'guide_id' => $guides[($index - 1) % 15]->id,
                    'vietnamese_guests' => $vietnameseGuests,
                    'international_guests' => $internationalGuests,
                    'coconut_portions' => $coconutPortions,
                    'ao_dai_units' => $index % 3 === 0 ? 1 : 0,
                    'ao_dai_revenue' => $index % 3 === 0 ? 50000 : 0,
                    'other_expenses' => 0,
                    'ticket_payment_method' => $paymentMethods[($index - 1) % count($paymentMethods)],
                    'notes' => $marker,
                    'photos' => $photos,
                ], $actor);

                $tour->update([
                    'status' => 'APPROVED',
                    'approved_by' => $actor->id,
                    'source_type' => TourGroup::SOURCE_DEMO,
                    'is_reconciled' => false,
                    'counts_in_dashboard' => false,
                ]);

                InventoryTransaction::query()->where('tour_group_id', $tour->id)
                    ->whereIn('transaction_type', ['TOUR_SALE', 'TOUR_RETURN'])
                    ->get()
                    ->each(fn (InventoryTransaction $transaction) => $transaction->update([
                        'notes' => $marker.':INVENTORY:'.$transaction->inventory_item_id,
                    ]));
            });
        }
    }
}
