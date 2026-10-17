<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of a product recipe. The ingredient itself always comes from the CENTRAL
 * database (a FoodSourceRecord) — a product never carries its own raw ingredient data.
 * Same shape as RecipeIngredient; see that class for the amount/food_form_id notes.
 */
class ProductRecipeIngredient extends Model
{
    protected $fillable = [
        'product_recipe_id', 'food_source_record_id', 'food_form_id', 'measure_unit_id',
        'food_portion_id', 'amount', 'is_added_after_cooking', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:4',
            'is_added_after_cooking' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function productRecipe(): BelongsTo
    {
        return $this->belongsTo(ProductRecipe::class);
    }

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

    /** The portion size picked on this line ("large"); null = the usual portion rules. */
    public function foodPortion(): BelongsTo
    {
        return $this->belongsTo(FoodPortion::class);
    }
}
