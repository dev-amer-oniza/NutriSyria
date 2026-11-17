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
        public int $brandId = 0,
        /** The restaurant's menu section it sits in, if any. */
        public ?int $categoryId = null,
        /** A dish of the platform's own kitchen ("المطبخ السوري"), approved with no review. */
        public bool $platform = false,
        /** Usually eaten with bread: its values are for the plate alone (4b). */
        public bool $withBread = false,
        /**
         * "من أين؟": by nutrient code, the ingredients that give it in this portion, largest
         * first (Contributions::rows). A restaurant's carry no amounts: ranks only.
         *
         * @var array<string, list<array{label: string, amount: ?float, pct: ?int}>>
         */
        public array $contributions = [],
        /**
         * Each ingredient of the portion with what it brings (Contributions::lines), to try
         * a change and judge it again (step 6). Never shown: a restaurant's holds only what goes
         * on top, and its suggestions name the new level, no amounts.
         *
         * @var list<array{label: string, after: bool, grams: ?float, nutrients: array<string, float>}>
         */
        public array $lines = [],
    ) {}

    /**
     * The same meal with one ingredient cut to $keep of itself (0 = without it): its values and
     * the portion's weight lose that part. For trying a suggestion; nothing else is changed.
     */
    public function without(array $line, float $keep = 0.0): self
    {
        $cut = 1 - $keep;
        $nutrients = $this->nutrients;
        foreach ($line['nutrients'] as $code => $amount) {
            if (isset($nutrients[$code])) {
                $nutrients[$code] = max(0.0, $nutrients[$code] - $amount * $cut);
            }
        }
        $serving = $this->servingGrams !== null && $line['grams'] !== null
            ? max(1.0, $this->servingGrams - $line['grams'] * $cut)
            : $this->servingGrams;

        return new self($this->id, $this->name, $this->brand, $this->description, $serving, $nutrients,
            $this->verification, $this->image, $this->allergens, $this->allergensChecked, $this->ingredients,
            $this->brandId, $this->categoryId, $this->platform, $this->withBread);
    }

    /** @return list<array{label: string, amount: ?float, pct: ?int}> */
    public function sourcesOf(string $nutrient): array
    {
        return $this->contributions[$nutrient] ?? [];
    }

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
    /** The restaurant's page on the site (null for a meal without one, e.g. a test double). */
    public function brandUrl(): ?string
    {
        if ($this->platform) {
            return route('site.kitchen');
        }

        return $this->brandId ? route('site.brands.show', $this->brandId) : null;
    }

    /** What the verification badge means for this meal: the kitchen's dishes have no review. */
    public function verificationText(): string
    {
        $level = config("site.verification.{$this->verification}");

        return $this->platform ? ($level['platform_text'] ?? $level['text']) : $level['text'];
    }

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
            'brandUrl' => $this->brandUrl(),
            'fit' => $fit?->level,
        ];
    }
}
