<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TourService extends Model
{
    protected $fillable = ['tour_group_id', 'service_type', 'quantity', 'revenue', 'commission_unit_price', 'commission_amount'];
}
