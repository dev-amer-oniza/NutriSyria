<?php

namespace App\Models;

use App\Enums\RecipeStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A dish's composition. Its calculated nutrients are NOT stored here: they live in
 * food_nutrient_values on sourceRecord, exactly like any other source (method = calculated).
 */
class Recipe extends Model
{
    protected $fillable = [
        'food_source_record_id', 'servings', 'weight_before_cooking_g',
        'weight_after_cooking_g', 'status', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'servings' => 'integer',
            'weight_before_cooking_g' => 'decimal:2',
            'weight_after_cooking_g' => 'decimal:2',
            'status' => RecipeStatus::class,
        ];
    }

    /** The record that carries this recipe's own identity, form and calculated nutrient values. */
    public function sourceRecord(): BelongsTo
    {
        return $this->belongsTo(FoodSourceRecord::class, 'food_source_record_id');
    }

    public function ingredients(): HasMany
    {
        return $this->hasMany(RecipeIngredient::class)->orderBy('sort_order');
    }

    /** The dish itself, e.g. "تبولة" — reached through its source record. */
    public function food(): ?Food
    {
        return $this->sourceRecord?->food;
    }
}
