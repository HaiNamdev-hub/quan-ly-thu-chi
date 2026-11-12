<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExcelImportBatch extends Model
{
    protected $fillable = ['source_name', 'source_sha256', 'storage_path', 'status', 'source_sheet', 'summary', 'imported_by', 'analyzed_at', 'confirmed_at'];

    protected function casts(): array
    {
        return ['summary' => 'array', 'analyzed_at' => 'datetime', 'confirmed_at' => 'datetime'];
    }

    public function importer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by');
    }

    public function rows(): HasMany
    {
        return $this->hasMany(ExcelImportRow::class);
    }
}
