<?php
namespace App\Services\Nutrition;

/**
 * The outcome of one RecipeCalculator::calculate() call. Carries absolute totals plus the
 * three derived views (per 100g before cooking, per 100g after cooking, per serving) —
 * see the discussion with the user: "after cooking" is the one that gets saved as the
 * official value; the others are sanity-check / informational views only.
 */
final readonly class RecipeAnalysisResult
{
    /**
     * @param  array<int, array{nutrient: array, amount: ?float, missing_from: string[]}>  $totals
     *     absolute amount for the WHOLE recipe, keyed by nutrient_id
     */
    public function __construct(
        public array $totals,
        public float $gramsBeforeCooking,
        public float $gramsTotal,
        public ?float $weightBeforeCookingG,
        public ?float $weightAfterCookingG,
        public int $servings,
        public ?string $weightWarning,
        /** @var list<array{label: string, problem: ?string}> lines with unknown grams, left out of every total */
        public array $unconvertedIngredients = [],
    ) {
    }

    /** Per 100g of the ingredient weights themselves (sanity view — should track the absolute totals closely). */
    public function per100gRaw(): array
    {
        return $this->scale($this->gramsTotal > 0 ? 100 / $this->gramsTotal : null);
    }

    /** Per 100g of the stated pre-cook weight. Diverges from per100gRaw() only if that weight was entered inaccurately. */
    public function per100gBeforeCooking(): array
    {
        return $this->scale($this->weightBeforeCookingG > 0 ? 100 / $this->weightBeforeCookingG : null);
    }

    /** Per 100g AS SERVED. This is the official value to store — water loss during cooking concentrates nutrients. */
    public function per100gAfterCooking(): array
    {
        return $this->scale($this->weightAfterCookingG > 0 ? 100 / $this->weightAfterCookingG : null);
    }

    public function perServing(): array
    {
        return $this->scale($this->servings > 0 ? 1 / $this->servings : null);
    }

    public function hasWeightAfterCooking(): bool
    {
        return $this->weightAfterCookingG !== null && $this->weightAfterCookingG > 0;
    }

    /** An ingredient with unknown grams would silently understate every value, so it blocks approval. */
    public function canSaveOfficialValue(): bool
    {
        return $this->hasWeightAfterCooking() && $this->unconvertedIngredients === [];
    }

    /** @return array<int, array{nutrient: array, amount: ?float, missing_from: string[]}> */
    private function scale(?float $factor): array
    {
        if ($factor === null) {
            return array_map(
                fn(array $row) => [ ...$row, 'amount' => null],
                $this->totals,
            );
        }

        return array_map(
            fn(array $row) => [
                 ...$row,
                'amount' => $row['amount'] !== null ? round($row['amount'] * $factor, 4) : null,
            ],
            $this->totals,
        );
    }
    public function toArray(): array
    {
        return [
            'totals'                  => $this->totals,
            'per_100g_raw'            => $this->per100gRaw(),
            'per_100g_before_cooking' => $this->per100gBeforeCooking(),
            'per_100g_after_cooking'  => $this->per100gAfterCooking(),
            'per_serving'             => $this->perServing(),
            'grams_before_cooking'    => $this->gramsBeforeCooking,
            'grams_total'             => $this->gramsTotal,
            'weight_before_cooking_g' => $this->weightBeforeCookingG,
            'weight_after_cooking_g'  => $this->weightAfterCookingG,
            'servings'                => $this->servings,
            'weight_warning'          => $this->weightWarning,
            'unconverted_ingredients' => $this->unconvertedIngredients,
            'has_weight_after_cooking' => $this->hasWeightAfterCooking(),
            'can_save_official_value' => $this->canSaveOfficialValue(),
        ];
    }
}
