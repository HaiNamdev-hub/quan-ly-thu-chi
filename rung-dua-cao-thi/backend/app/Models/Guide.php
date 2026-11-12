<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Guide extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['name', 'phone', 'notes', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function tours(): HasMany
    {
        return $this->hasMany(TourGroup::class);
    }
}
