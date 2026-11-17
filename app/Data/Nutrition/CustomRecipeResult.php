<?php

namespace App\Data\Nutrition;

/**
 * A visitor's own version of a kitchen dish, analysed and never stored (the owner's decisions
 * 1a-6): the values of one of their servings, ours beside them, and how it suits them.
 */
final readonly class CustomRecipeResult
{
    /**
     * @param  list<array{label: string, problem: ?string}>  $unconverted  lines left out: no grams, so not counted as zero
     */
    public function __construct(
        /** Their dish, per one of their servings (per 100 g when no cooked weight could be had). */
        public MealFacts $meal,
        /** Our approved dish, for the comparison (4a). */
        public MealFacts $original,
        /** The cooked weight was estimated from ours, not weighed (3a). */
        public bool $weightEstimated,
        public float $weightAfterCookingG,
        public array $unconverted = [],
        public ?MealFit $fit = null,
        /** @var list<MealSuggestion> what would help their own recipe more (step 6, 3a) */
        public array $suggestions = [],
        /** The level our recipe gets for them, to say what it was ("كانت: …"). */
        public ?int $originalLevel = null,
    ) {}
}
