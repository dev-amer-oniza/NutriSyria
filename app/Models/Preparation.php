<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A brand's own preparation (كريمة تمر, a sauce, a dough): foods and amounts written once and
 * used as one ingredient in its products. Never shown or approved on its own: the analysis of
 * each product opens it into its foods, and the nutritionist approves the product.
 */
class Preparation extends Model
{
    protected $fillable = ['brand_id', 'name', 'note', 'weight_after_g'];

    protected function casts(): array
    {
        return ['weight_after_g' => 'decimal:3'];
    }

    /** @return BelongsTo<Brand, $this> */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /** @return HasMany<PreparationIngredient, $this> */
    public function ingredients(): HasMany
    {
        return $this->hasMany(PreparationIngredient::class)->orderBy('sort_order');
    }

    /**
     * The product lines that use it.
     *
     * @return HasMany<ProductRecipeIngredient, $this>
     */
    public function productLines(): HasMany
    {
        return $this->hasMany(ProductRecipeIngredient::class);
    }

    /**
     * Why it cannot be deleted now (the owner's decision 2a), or null.
     */
    public function deletionBlocker(): ?string
    {
        $names = Product::query()
            ->whereHas('recipe.ingredients', fn ($q) => $q->where('preparation_id', $this->getKey()))
            ->orderBy('name')->pluck('name');

        return $names->isEmpty() ? null
            : 'مستعمل بـ: '.$names->take(5)->implode('، ').($names->count() > 5 ? ' و'.($names->count() - 5).' غيرها' : '')
              .'. شيله من وصفاتها أول، بعدين احذفه.';
    }
}
