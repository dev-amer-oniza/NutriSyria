<?php

namespace App\Models;

use App\Enums\RecipeStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $product_id
 * @property int|null $servings
 * @property string|null $weight_before_cooking_g
 * @property string|null $weight_after_cooking_g
 * @property Carbon|null $nutrition_approved_at
 * @property string|null $review_requested_at when the nutritionists were last told it waits for them
 * @property array<string, list<array{label: string, amount: float, after: bool}>>|null $contributions what each ingredient brings to the whole recipe (step 4)
 *
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
            'contributions' => 'array',
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
