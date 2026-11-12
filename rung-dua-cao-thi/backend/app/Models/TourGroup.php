<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TourGroup extends Model
{
    use HasFactory, SoftDeletes;

    public const SOURCE_REAL_IMPORTED = 'REAL_IMPORTED';

    public const SOURCE_MANUAL_ENTRY = 'MANUAL_ENTRY';

    public const SOURCE_DEMO = 'DEMO';

    protected $appends = ['reconciliation_status'];

    protected $fillable = [
        'tour_date', 'sequence', 'code', 'guide_id', 'created_by', 'updated_by', 'approved_by', 'locked_by',
        'vietnamese_guests', 'international_guests', 'total_guests', 'paid_tickets', 'boats',
        'ticket_vietnamese_unit_price', 'ticket_international_unit_price', 'ticket_cost_unit_price', 'boat_cost_unit_price',
        'ticket_revenue', 'ticket_cost', 'boat_cost', 'door_commission', 'coconut_portions', 'ao_dai_units',
        'coconut_commission_unit_price', 'ao_dai_commission_unit_price', 'ao_dai_revenue', 'photo_revenue',
        'photo_cost', 'photo_commission', 'other_expenses', 'ticket_payment_method', 'status', 'notes', 'unlock_reason',
        'source_type', 'is_reconciled', 'counts_in_dashboard', 'excel_import_row_id',
    ];

    protected function casts(): array
    {
        return ['tour_date' => 'date', 'is_reconciled' => 'boolean', 'counts_in_dashboard' => 'boolean'];
    }

    protected function sourceType(): Attribute
    {
        return Attribute::make(
            get: static fn (?string $value): ?string => match ($value) {
                'excel' => self::SOURCE_REAL_IMPORTED,
                'manual' => self::SOURCE_MANUAL_ENTRY,
                'demo' => self::SOURCE_DEMO,
                default => $value,
            },
        );
    }

    protected function reconciliationStatus(): Attribute
    {
        return Attribute::make(
            get: fn (): string => $this->is_reconciled ? 'RECONCILED' : 'PENDING_RECONCILIATION',
        );
    }

    public function scopeExcludeDemo(Builder $query): Builder
    {
        return $query
            ->where(function (Builder $query): void {
                $query->whereNull('source_type')->orWhereNotIn('source_type', [self::SOURCE_DEMO, 'demo']);
            })
            ->where(function (Builder $query): void {
                $query->whereNull('notes')->orWhere('notes', 'not like', 'DEMO-SEED:%');
            });
    }

    public function guide(): BelongsTo
    {
        return $this->belongsTo(Guide::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function locker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by');
    }

    public function services(): HasMany
    {
        return $this->hasMany(TourService::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(TourPhoto::class);
    }

    public function excelImportRow(): BelongsTo
    {
        return $this->belongsTo(ExcelImportRow::class);
    }
}
