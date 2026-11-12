<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryTransaction extends Model
{
    protected $fillable = ['inventory_item_id', 'transaction_type', 'quantity_delta', 'tour_group_id', 'actor_id', 'notes'];

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function tour(): BelongsTo
    {
        return $this->belongsTo(TourGroup::class, 'tour_group_id');
    }
}
