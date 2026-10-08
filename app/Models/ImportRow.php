<?php

namespace App\Models;

use App\Enums\ImportRowStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One raw CSV row of one import, kept permanently as evidence for whatever it produced. */
class ImportRow extends Model
{
    protected $fillable = ['import_batch_id', 'sheet', 'row_number', 'raw', 'status', 'errors'];

    protected function casts(): array
    {
        return [
            'raw' => 'array',
            'status' => ImportRowStatus::class,
            'errors' => 'array',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class, 'import_batch_id');
    }
}
