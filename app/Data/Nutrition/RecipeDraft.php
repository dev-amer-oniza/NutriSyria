<?php

namespace App\Data\Nutrition;

/**
 * A recipe as the analysis reads it, whoever wrote it: a product's saved recipe in the panel,
 * or a visitor's own version of a kitchen dish on the site, which is never stored. Both go
 * through the very same calculation (RecipeAnalysisService::analyzeDraft).
 */
final readonly class RecipeDraft
{
    /** @param  list<RecipeLine>  $lines */
    public function __construct(
        public array $lines,
        public int $servings = 1,
        public ?float $weightBeforeCookingG = null,
        public ?float $weightAfterCookingG = null,
    ) {}

    /** The same recipe with a cooked weight (a visitor's estimate). */
    public function withWeightAfterCooking(?float $grams): self
    {
        return new self($this->lines, $this->servings, $this->weightBeforeCookingG, $grams);
    }
}
