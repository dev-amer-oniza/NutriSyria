<?php

namespace App\Services\Site;

use App\Data\Nutrition\MealFacts;
use App\Enums\NutrientValueMethod;
use App\Enums\NutritionApprovalStatus;
use App\Models\Product;
use App\Models\ProductRecipeIngredient;
use App\Services\Import\ArabicNormalizer;
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
    public function __construct(private readonly PublicCatalogCache $cache) {}

    /** Nutrient codes read for a meal: what cards show and what meals are judged on. */
    public const NUTRIENTS = [
        'energy_kcal', 'protein_g', 'carbohydrate_g', 'fat_g', 'fiber_g',
        'fat_sat_g', 'sugars_g', 'sodium_mg', 'iron_mg', 'calcium_mg',
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
        return array_map(fn (MealFacts $meal) => $meal->card(), $this->meals($limit));
    }

    /**
     * Approved meals, newest approval first; optionally those whose name or restaurant
     * matches a search (Arabic spelling variants aside). Read from the shared cache.
     *
     * @return list<MealFacts>
     */
    public function meals(?int $limit = null, ?string $search = null): array
    {
        $meals = $this->all();

        if (filled($search)) {
            $term = ArabicNormalizer::normalize($search);
            $meals = array_values(array_filter($meals, fn (MealFacts $m) => str_contains(ArabicNormalizer::normalize($m->name), $term)
                || str_contains(ArabicNormalizer::normalize($m->brand), $term)));
        }

        return $limit ? array_slice($meals, 0, $limit) : $meals;
    }

    /** One public meal, or null (unapproved, changed since approval, or hidden). */
    public function find(int $id): ?MealFacts
    {
        foreach ($this->all() as $meal) {
            if ($meal->id === $id) {
                return $meal;
            }
        }

        return null;
    }

    /** @return list<MealFacts> a restaurant's approved meals, newest approval first */
    public function ofBrand(int $brandId): array
    {
        return array_values(array_filter($this->all(), fn (MealFacts $m) => $m->brandId === $brandId));
    }

    /** @return list<MealFacts> every public meal, built once per catalog version */
    private function all(): array
    {
        return $this->cache->remember('meals', fn () => $this->approved()
            ->map(fn (Product $product) => $this->facts($product))
            ->values()
            ->all());
    }

    /** @return Collection<int, Product> */
    private function approved(): Collection
    {
        return NutritionApprovalStatus::withApprovalData($this->publicProducts())
            ->with([
                'brand:id,name',
                'nutrientValues' => fn ($q) => $q->with('nutrient:id,code')
                    ->whereHas('nutrient', fn ($n) => $n->whereIn('code', self::NUTRIENTS)),
                'recipeIngredients.ingredientRecord.food:id,name_ar,allergens,allergens_checked',
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

    private function facts(Product $product): MealFacts
    {
        $serving = $this->servingGrams($product);
        $factor = ($serving ?? 100) / 100;
        $foods = $product->recipeIngredients
            ->map(fn (ProductRecipeIngredient $line) => $line->ingredientRecord?->food)
            ->filter()
            ->unique('id');

        return new MealFacts(
            id: $product->id,
            name: $product->name,
            brand: $product->brand->name,
            description: $product->description,
            servingGrams: $serving,
            nutrients: $product->nutrientValues
                ->mapWithKeys(fn ($value) => [$value->nutrient->code => (float) $value->amount_per_100g * $factor])
                ->all(),
            verification: $this->verification($product),
            image: $product->img ? Storage::disk('public')->url($product->img) : null,
            allergens: $foods->flatMap(fn ($food) => $food->allergens ?? [])->unique()->values()->all(),
            allergensChecked: $foods->isNotEmpty() && $foods->every(fn ($food) => $food->allergens_checked),
            ingredients: $foods->pluck('name_ar')->values()->all(),
            brandId: $product->brand_id,
        );
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
