<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One free-text unit label (e.g. "cup, chopped") mapped to one measure unit. */
class MeasureUnitAlias extends Model
{
    protected $fillable = ['measure_unit_id', 'label'];

    public function measureUnit(): BelongsTo
    {
        return $this->belongsTo(MeasureUnit::class);
    }
}
