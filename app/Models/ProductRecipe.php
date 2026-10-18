<?php

namespace App\Models;

use App\Enums\RecipeStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A brand's product recipe. Mirrors Recipe, but belongs to a Product instead of a
 * FoodSourceRecord — the product is a commercial item, not a general food.
 */
class ProductRecipe extends Model
{
    protected $fillable = [
        'product_id', 'servings', 'weight_before_cooking_g',
        'weight_after_cooking_g', 'status', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'servings' => 'integer',
            'weight_before_cooking_g' => 'decimal:2',
            'weight_after_cooking_g' => 'decimal:2',
            'status' => RecipeStatus::class,
            'nutrition_approved_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function ingredients(): HasMany
    {
        return $this->hasMany(ProductRecipeIngredient::class)->orderBy('sort_order');
    }

    /** Who saved the current official nutrition values (set by RecipeAnalysisService::approve). */
    public function nutritionApprovedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'nutrition_approved_by');
    }
}
