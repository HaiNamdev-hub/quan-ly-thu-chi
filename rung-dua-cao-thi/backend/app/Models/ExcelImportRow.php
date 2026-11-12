<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExcelImportRow extends Model
{
    protected $fillable = ['excel_import_batch_id', 'source_sheet', 'source_row', 'row_sha256', 'status', 'source_values', 'mapped_values', 'issues', 'tour_group_id'];

    protected function casts(): array
    {
        return ['source_values' => 'array', 'mapped_values' => 'array', 'issues' => 'array'];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ExcelImportBatch::class, 'excel_import_batch_id');
    }

    public function tour(): BelongsTo
    {
        return $this->belongsTo(TourGroup::class, 'tour_group_id');
    }
}
