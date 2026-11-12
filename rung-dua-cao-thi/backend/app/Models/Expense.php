<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Expense extends Model
{
    use SoftDeletes;

    protected $fillable = ['category', 'amount', 'occurred_on', 'tour_group_id', 'created_by', 'updated_by', 'deleted_by', 'notes'];

    protected function casts(): array
    {
        return ['occurred_on' => 'date'];
    }

    public function scopeExcludeDemo(Builder $query): Builder
    {
        return $query->where(function (Builder $query): void {
            $query->whereNull('notes')->orWhere('notes', 'not like', 'DEMO-SEED:%');
        });
    }

    public function tour(): BelongsTo
    {
        return $this->belongsTo(TourGroup::class, 'tour_group_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
