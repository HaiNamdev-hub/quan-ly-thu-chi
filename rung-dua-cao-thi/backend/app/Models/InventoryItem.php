<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryItem extends Model
{
    protected $fillable = ['sku', 'name', 'unit', 'opening_quantity', 'current_quantity', 'low_stock_threshold', 'stock_control_enabled', 'is_active'];

    protected function casts(): array
    {
        return ['stock_control_enabled' => 'boolean', 'is_active' => 'boolean'];
    }
}
