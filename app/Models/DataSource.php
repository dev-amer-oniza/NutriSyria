<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One trusted dataset (USDA SR Legacy, a Syrian lab, internal recipes...). Provenance and
 * trust ranking (priority) live here, not on each record, so re-ranking a source is one
 * row update instead of thousands (see decisions-and-principles.md).
 */
class DataSource extends Model
{
    protected $fillable = [
        'code', 'name', 'type', 'publisher', 'version', 'published_at',
        'country', 'url', 'license', 'citation', 'priority', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'date',
            'priority' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function foodSourceRecords(): HasMany
    {
        return $this->hasMany(FoodSourceRecord::class);
    }
}
