<?php

namespace App\Data\Nutrition;

/**
 * One public meal as the site shows and judges it: its values for one serving (or for
 * 100 g when the recipe lacks a cooked weight or servings), and what its ingredients hold.
 */
final readonly class MealFacts
{
    /**
     * @param  array<string, float>  $nutrients  nutrient code => amount in one portion
     * @param  list<string>  $allergens  App\Enums\Allergen values found in its ingredients
     * @param  list<string>  $ingredients  ingredient food names
     */
    public function __construct(
        public int $id,
        public string $name,
        public string $brand,
        public ?string $description,
        /** One serving after cooking; null when unknown (the values are then per 100 g). */
        public ?float $servingGrams,
        public array $nutrients,
        /** verified | calculated | estimated (config/site.php verification) */
        public string $verification,
        public ?string $image,
        public array $allergens = [],
        /** Every ingredient food has had its allergens checked. */
        public bool $allergensChecked = false,
        public array $ingredients = [],
    ) {}

    /** The grams the values describe. */
    public function portionGrams(): float
    {
        return $this->servingGrams ?? 100.0;
    }

    public function amount(string $nutrient): ?float
    {
        return $this->nutrients[$nutrient] ?? null;
    }

    /** For site.components.dish-card. */
    public function card(?MealFit $fit = null): array
    {
        $rounded = fn (string $code) => isset($this->nutrients[$code]) ? (int) round($this->nutrients[$code]) : null;

        return [
            'key' => $this->id,
            'name' => $this->name,
            'brand' => $this->brand,
            'description' => $this->description,
            'portion' => (int) round($this->portionGrams()),
            'kcal' => $rounded('energy_kcal'),
            'protein' => $rounded('protein_g'),
            'carbs' => $rounded('carbohydrate_g'),
            'fat' => $rounded('fat_g'),
            'fiber' => $rounded('fiber_g'),
            'verification' => $this->verification,
            'image' => $this->image,
            'url' => route('site.meals.show', $this->id),
            'fit' => $fit?->level,
        ];
    }
}
