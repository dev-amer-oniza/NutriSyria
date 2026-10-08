<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of a recipe: an existing food_source_record used as an ingredient, in a given
 * amount and unit. amount is in measure_unit's unit, NOT grams — resolving grams requires
 * joining through food_portions (see decisions-and-principles.md).
 *
 * food_form_id must equal ingredientRecord.food_form_id or be null; the app enforces this,
 * it is not a database constraint.
 */
class RecipeIngredient extends Model
{
    protected $fillable = [
        'recipe_id', 'food_source_record_id', 'food_form_id', 'measure_unit_id',
        'amount', 'is_added_after_cooking', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:4',
            'is_added_after_cooking' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }

    /** The ingredient itself (e.g. garlic, raw — a food_source_record, not a Food directly). */
    public function ingredientRecord(): BelongsTo
    {
        return $this->belongsTo(FoodSourceRecord::class, 'food_source_record_id');
    }

    public function foodForm(): BelongsTo
    {
        return $this->belongsTo(FoodForm::class);
    }

    public function measureUnit(): BelongsTo
    {
        return $this->belongsTo(MeasureUnit::class);
    }
}
