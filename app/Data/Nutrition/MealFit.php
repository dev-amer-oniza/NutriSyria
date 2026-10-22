<?php

namespace App\Data\Nutrition;

/** How well one meal fits one person (MealFitCalculator). */
final readonly class MealFit
{
    /**
     * @param  int  $level  4 suits very well … 1 not for you now (config/site.php fit_levels)
     * @param  list<string>  $reasons  why, most important first
     * @param  array{energy: ?int, protein: ?int, carbs: ?int, fat: ?int}  $share  percent of the day's needs
     * @param  array{grams: int, kcal: ?int, whole: bool}|null  $portion  the largest portion within the limits; null when none suits
     * @param  list<string>  $notes  what is missing from the meal, or values we lack
     */
    public function __construct(
        public int $level,
        public array $reasons,
        public array $share,
        public ?array $portion,
        public array $notes,
        public string $template,
    ) {}
}
