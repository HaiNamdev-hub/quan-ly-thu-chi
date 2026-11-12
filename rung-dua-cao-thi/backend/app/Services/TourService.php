<?php

namespace App\Services;

use App\Models\TourGroup;
use App\Models\TourPhoto;
use App\Models\TourService as TourServiceLine;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TourService
{
    public function __construct(private InventoryService $inventoryService) {}

    /** @param array<string, mixed> $data */
    public function create(array $data, User $actor): TourGroup
    {
        return DB::transaction(function () use ($data, $actor): TourGroup {
            $date = Carbon::parse($data['tour_date'])->toDateString();
            DB::table('tour_counters')->insertOrIgnore([
                'tour_date' => $date,
                'last_sequence' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $counter = DB::table('tour_counters')->where('tour_date', $date)->lockForUpdate()->first();
            $nextSequence = (int) $counter->last_sequence + 1;

            if ($nextSequence > 20) {
                throw ValidationException::withMessages(['tour_date' => ['Ngày này đã đủ 20 đoàn.']]);
            }

            DB::table('tour_counters')->where('tour_date', $date)->update(['last_sequence' => $nextSequence, 'updated_at' => now()]);
            $tour = new TourGroup([
                ...$this->calculate($data, $date),
                'tour_date' => $date,
                'sequence' => $nextSequence,
                'code' => sprintf('CT-%02d-%s', $nextSequence, Carbon::parse($date)->format('d/m')),
                'guide_id' => $data['guide_id'] ?? null,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
                'status' => 'DRAFT',
                'source_type' => TourGroup::SOURCE_MANUAL_ENTRY,
                'ticket_payment_method' => $data['ticket_payment_method'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);
            $tour->save();
            $this->saveLines($tour, $data, $date);
            $this->inventoryService->reconcileTourPhotos($tour, $data['photos'] ?? [], $actor);

            return $tour->load(['guide', 'services', 'photos']);
        });
    }

    /** @param array<string, mixed> $data */
    public function update(TourGroup $tour, array $data, User $actor): TourGroup
    {
        return DB::transaction(function () use ($tour, $data, $actor): TourGroup {
            $date = Carbon::parse($data['tour_date'])->toDateString();
            $newDate = $date !== $tour->tour_date->toDateString();
            $sequence = $tour->sequence;

            if ($newDate) {
                $date = Carbon::parse($data['tour_date'])->toDateString();
                DB::table('tour_counters')->insertOrIgnore([
                    'tour_date' => $date, 'last_sequence' => 0, 'created_at' => now(), 'updated_at' => now(),
                ]);
                $counter = DB::table('tour_counters')->where('tour_date', $date)->lockForUpdate()->first();
                $sequence = (int) $counter->last_sequence + 1;
                if ($sequence > 20) {
                    throw ValidationException::withMessages(['tour_date' => ['Ngày này đã đủ 20 đoàn.']]);
                }
                DB::table('tour_counters')->where('tour_date', $date)->update(['last_sequence' => $sequence, 'updated_at' => now()]);
            }

            $tour->fill([
                ...$this->calculate($data, $date),
                'tour_date' => $date,
                'sequence' => $sequence,
                'code' => sprintf('CT-%02d-%s', $sequence, Carbon::parse($date)->format('d/m')),
                'guide_id' => $data['guide_id'] ?? null,
                'updated_by' => $actor->id,
                'ticket_payment_method' => $data['ticket_payment_method'] ?? null,
                'notes' => $data['notes'] ?? null,
            ])->save();
            $tour->services()->delete();
            $tour->photos()->delete();
            $this->saveLines($tour, $data, $date);
            $this->inventoryService->reconcileTourPhotos($tour, $data['photos'] ?? [], $actor);

            return $tour->refresh()->load(['guide', 'services', 'photos']);
        });
    }

    /** @param array<string, mixed> $data
     * @return array<string, int>
     */
    private function calculate(array $data, string $date): array
    {
        $vietnameseGuests = (int) $data['vietnamese_guests'];
        $internationalGuests = (int) $data['international_guests'];
        $totalGuests = $vietnameseGuests + $internationalGuests;
        $vietnamesePrice = $this->ticketPrice('VIETNAMESE', $date, 'ticket_price');
        $internationalPrice = $this->ticketPrice('INTERNATIONAL', $date, 'ticket_price');
        $ticketCostPrice = $this->ticketPrice('VIETNAMESE', $date, 'ticket_cost');
        $paidTickets = $totalGuests - intdiv($totalGuests, 10);
        $guestsPerBoat = $this->operationalRule('guests_per_boat', $date);
        $boatCostPrice = $this->operationalRule('boat_cost_per_boat', $date);
        $boats = intdiv($totalGuests + $guestsPerBoat - 1, $guestsPerBoat);
        $doorRule = $this->commission($totalGuests > 10 ? 'door_above_threshold' : 'door_up_to_threshold', $date);
        $coconutCommission = $this->commission('coconut_per_portion', $date);
        $aoDaiCommission = $this->commission('ao_dai_per_unit', $date);

        return [
            'vietnamese_guests' => $vietnameseGuests,
            'international_guests' => $internationalGuests,
            'total_guests' => $totalGuests,
            'paid_tickets' => $paidTickets,
            'boats' => $boats,
            'ticket_vietnamese_unit_price' => $vietnamesePrice,
            'ticket_international_unit_price' => $internationalPrice,
            'ticket_cost_unit_price' => $ticketCostPrice,
            'boat_cost_unit_price' => $boatCostPrice,
            'ticket_revenue' => $vietnameseGuests * $vietnamesePrice + $internationalGuests * $internationalPrice,
            'ticket_cost' => $paidTickets * $ticketCostPrice,
            'boat_cost' => $boats * $boatCostPrice,
            'door_commission' => $totalGuests === 0 ? 0 : $doorRule,
            'coconut_portions' => (int) ($data['coconut_portions'] ?? 0),
            'ao_dai_units' => (int) ($data['ao_dai_units'] ?? 0),
            'coconut_commission_unit_price' => $coconutCommission,
            'ao_dai_commission_unit_price' => $aoDaiCommission,
            'ao_dai_revenue' => (int) ($data['ao_dai_revenue'] ?? 0),
            'other_expenses' => (int) ($data['other_expenses'] ?? 0),
        ];
    }

    /** @param array<string, mixed> $data */
    private function saveLines(TourGroup $tour, array $data, string $date): void
    {
        $coconuts = (int) ($data['coconut_portions'] ?? 0);
        $aoDai = (int) ($data['ao_dai_units'] ?? 0);
        TourServiceLine::create([
            'tour_group_id' => $tour->id,
            'service_type' => 'coconut',
            'quantity' => $coconuts,
            'revenue' => 0,
            'commission_unit_price' => $tour->coconut_commission_unit_price,
            'commission_amount' => $coconuts * $tour->coconut_commission_unit_price,
        ]);
        TourServiceLine::create([
            'tour_group_id' => $tour->id,
            'service_type' => 'ao_dai',
            'quantity' => $aoDai,
            'revenue' => $tour->ao_dai_revenue,
            'commission_unit_price' => $tour->ao_dai_commission_unit_price,
            'commission_amount' => $aoDai * $tour->ao_dai_commission_unit_price,
        ]);

        $photoCounts = $data['photos'] ?? [];
        $internationalPriceApplies = (int) $tour->international_guests > 0;
        $photoRevenue = 0;
        $photoCost = 0;
        $photoCommission = 0;

        foreach (['large_frame', 'small_frame', 'large_plastic', 'small_plastic'] as $photoType) {
            $quantity = (int) ($photoCounts[$photoType] ?? 0);
            if ($quantity === 0) {
                continue;
            }
            $price = DB::table('photo_price_histories')->where('photo_type', $photoType)
                ->whereDate('effective_from', '<=', $date)
                ->where(fn ($query) => $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $date))
                ->orderByDesc('effective_from')->first();
            if ($price === null) {
                throw ValidationException::withMessages(['photos' => ['Chưa cấu hình bảng giá ảnh cho ngày đoàn.']]);
            }
            $unitPrice = $internationalPriceApplies ? $price->international_price : $price->vietnamese_price;
            $unitCommission = str_contains($photoType, 'plastic') ? $this->commission('plastic_photo_per_unit', $date) : 0;
            $lineRevenue = $quantity * (int) $unitPrice;
            $lineCost = $quantity * (int) $price->unit_cost;
            $lineCommission = $quantity * $unitCommission;
            TourPhoto::create([
                'tour_group_id' => $tour->id,
                'photo_type' => $photoType,
                'quantity' => $quantity,
                'unit_price_snapshot' => $unitPrice,
                'unit_cost_snapshot' => $price->unit_cost,
                'revenue' => $lineRevenue,
                'cost' => $lineCost,
                'commission' => $lineCommission,
            ]);
            $photoRevenue += $lineRevenue;
            $photoCost += $lineCost;
            $photoCommission += $lineCommission;
        }

        $tour->forceFill(['photo_revenue' => $photoRevenue, 'photo_cost' => $photoCost, 'photo_commission' => $photoCommission])->save();
    }

    private function ticketPrice(string $customerType, string $date, string $column): int
    {
        $price = DB::table('ticket_price_histories')->where('customer_type', $customerType)
            ->whereDate('effective_from', '<=', $date)
            ->where(fn ($query) => $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $date))
            ->orderByDesc('effective_from')->value($column);

        if ($price === null) {
            throw ValidationException::withMessages(['tour_date' => ['Chưa cấu hình giá vé cho ngày đoàn.']]);
        }

        return (int) $price;
    }

    private function commission(string $key, string $date): int
    {
        $amount = DB::table('commission_rules')->where('rule_key', $key)
            ->whereDate('effective_from', '<=', $date)
            ->where(fn ($query) => $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $date))
            ->orderByDesc('effective_from')->value('amount');

        if ($amount === null) {
            throw ValidationException::withMessages(['tour_date' => ['Chưa cấu hình mức hoa hồng cho ngày đoàn.']]);
        }

        return (int) $amount;
    }

    private function operationalRule(string $key, string $date): int
    {
        $value = DB::table('operational_rules')->where('rule_key', $key)
            ->whereDate('effective_from', '<=', $date)
            ->where(fn ($query) => $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $date))
            ->orderByDesc('effective_from')->value('value');

        if ($value === null || (int) $value < 1) {
            throw ValidationException::withMessages(['tour_date' => ['Chưa cấu hình quy tắc vận hành cho ngày đoàn.']]);
        }

        return (int) $value;
    }
}
