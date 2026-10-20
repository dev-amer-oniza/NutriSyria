<?php

namespace App\Services\Site;

use App\Enums\NutrientValueMethod;
use App\Enums\NutritionApprovalStatus;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Meals shown on the public site. Only products whose official values are
 * approved by a nutritionist and still match the recipe (status Approved),
 * from active products of active brands.
 */
class MealCatalog
{
    /** Nutrient codes read for a meal card, mapped to the card's keys. */
    private const NUTRIENTS = [
        'energy_kcal' => 'kcal',
        'protein_g' => 'protein',
        'carbohydrate_g' => 'carbs',
        'fat_g' => 'fat',
        'fiber_g' => 'fiber',
    ];

    /** Notes of a food record that stands in for a food USDA does not have start with this. */
    public const PROXY_NOTE_PREFIX = 'مقاربة';

    /**
     * The newest approved meals, as dish cards (site.components.dish-card).
     *
     * @return list<array<string, mixed>>
     */
    public function latest(int $limit = 8): array
    {
        return $this->approved()
            ->take($limit)
            ->map(fn (Product $product) => $this->card($product))
            ->values()
            ->all();
    }

    /** @return Collection<int, Product> */
    private function approved(): Collection
    {
        return NutritionApprovalStatus::withApprovalData($this->publicProducts())
            ->with([
                'brand:id,name',
                'nutrientValues' => fn ($q) => $q->with('nutrient:id,code')
                    ->whereHas('nutrient', fn ($n) => $n->whereIn('code', array_keys(self::NUTRIENTS))),
            ])
            ->withExists(['recipeIngredients as uses_proxy' => fn (Builder $q) => $q
                ->whereHas('ingredientRecord', fn (Builder $r) => $r->where('notes', 'like', self::PROXY_NOTE_PREFIX.'%'))])
            ->get()
            // "Changed after approval" values no longer describe the recipe: keep them off the site.
            ->filter(fn (Product $product) => NutritionApprovalStatus::of($product) === NutritionApprovalStatus::Approved)
            ->sortByDesc(fn (Product $product) => $product->recipe?->nutrition_approved_at);
    }

    private function publicProducts(): Builder
    {
        return Product::query()
            ->where('products.is_active', true)
            ->whereHas('brand', fn (Builder $q) => $q->where('is_active', true));
    }

    /** @return array<string, mixed> */
    private function card(Product $product): array
    {
        $per100g = $product->nutrientValues
            ->mapWithKeys(fn ($value) => [self::NUTRIENTS[$value->nutrient->code] => (float) $value->amount_per_100g]);

        $serving = $this->servingGrams($product);
        $factor = ($serving ?? 100) / 100;
        $amount = fn (string $key) => $per100g->has($key) ? (int) round($per100g[$key] * $factor) : null;

        return [
            'key' => $product->id,
            'name' => $product->name,
            'brand' => $product->brand->name,
            'description' => $product->description,
            'portion' => $serving !== null ? (int) round($serving) : 100,
            'kcal' => $amount('kcal'),
            'protein' => $amount('protein'),
            'carbs' => $amount('carbs'),
            'fat' => $amount('fat'),
            'fiber' => $amount('fiber'),
            'verification' => $this->verification($product),
            'image' => $product->img ? Storage::disk('public')->url($product->img) : null,
            'url' => route('site.meals'),
        ];
    }

    /** One serving after cooking; null when the recipe lacks a cooked weight or servings. */
    private function servingGrams(Product $product): ?float
    {
        $recipe = $product->recipe;
        $weight = (float) ($recipe?->weight_after_cooking_g ?? 0);
        $servings = (int) ($recipe?->servings ?? 0);

        return $weight > 0 && $servings > 0 ? $weight / $servings : null;
    }

    /**
     * verified = the dish itself has laboratory values; estimated = an ingredient
     * stands in for a food we lack (a proxy); otherwise calculated.
     */
    private function verification(Product $product): string
    {
        if ($product->nutrientValues->contains(fn ($value) => $value->method === NutrientValueMethod::Laboratory)) {
            return 'verified';
        }

        return $product->uses_proxy ? 'estimated' : 'calculated';
    }
}
