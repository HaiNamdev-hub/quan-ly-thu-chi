<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TourPhoto extends Model
{
    protected $fillable = ['tour_group_id', 'photo_type', 'quantity', 'unit_price_snapshot', 'unit_cost_snapshot', 'revenue', 'cost', 'commission'];
}
