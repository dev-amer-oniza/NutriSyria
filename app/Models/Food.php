<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/**
 * A food CONCEPT ("chickpeas", "tabbouleh"). Its numbers live in foodSourceRecords, one per
 * source and form. Whether it is a "dish" or a plain ingredient is never stored — see
 * $isRecipe below — matching decisions-and-principles.md.
 */
class Food extends Model
{
    protected $fillable = ['name_ar', 'name_en', 'scientific_name', 'food_category_id', 'img', 'is_active'];
    protected $table = 'foods'; 

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
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

    /** Every recipe attached to any of this food's source records (normally at most one). */
    public function recipes(): HasManyThrough
    {
        return $this->hasManyThrough(Recipe::class, FoodSourceRecord::class, 'food_id', 'food_source_record_id');
    }

    /**
     * Computed, not stored: a food IS a dish when one of its source records has a recipe.
     * Kept as a query (not eager-loadable count) so it stays correct without a sync step.
     */
    protected function isRecipe(): Attribute
    {
        return Attribute::make(get: fn () => $this->recipes()->exists());
    }

    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
