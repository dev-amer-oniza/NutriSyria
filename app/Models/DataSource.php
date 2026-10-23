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

    /**
     * Why this source may not be deleted, or null when it may: a source holding records is
     * where approved numbers come from (the owner's decision 1a), so it stays.
     */
    public function deletionBlocker(): ?string
    {
        $count = $this->foodSourceRecords()->count();

        return $count > 0
            ? "لا يمكن حذف «{$this->name}»: عليه {$count} سجلاً، وكل رقم معتمد يحتاج مصدره. أوقفه بدل حذفه إن لم تعد تستعمله."
            : null;
    }

    public function foodSourceRecords(): HasMany
    {
        return $this->hasMany(FoodSourceRecord::class);
    }
}
