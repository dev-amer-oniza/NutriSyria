<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A food CONCEPT ("chickpeas", "tabbouleh"). Its numbers live in foodSourceRecords, one per
 * source and form.
 */
class Food extends Model
{
    protected $fillable = ['name_ar', 'name_en', 'scientific_name', 'food_category_id', 'img', 'is_active', 'allergens', 'allergens_checked'];

    protected $table = 'foods';

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'allergens' => 'array', 'allergens_checked' => 'boolean'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(FoodCategory::class, 'food_category_id');
    }

    public function sourceRecords(): HasMany
    {
        return $this->hasMany(FoodSourceRecord::class);
    }

    public function portions(): HasMany
    {
        return $this->hasMany(FoodPortion::class);
    }

    public function aliases(): HasMany
    {
        return $this->hasMany(FoodAlias::class);
    }

    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
