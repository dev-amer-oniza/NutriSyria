<?php

namespace App\Data\Nutrition;

/** What a nutrition plan works out to for one client (NutritionPlanCalculator). */
final readonly class NutritionPlanResult
{
    /**
     * @param  array<string, array{pct: int, g: int, kcal: int}>  $macros  carbs, protein, fat
     * @param  array{ml: ?int, suggested: ?int, manual: bool}  $fluids  manual: the nutritionist must type it
     * @param  list<string>  $warnings  shown, but the plan may be saved
     * @param  list<string>  $errors  the plan cannot be saved until fixed
     */
    public function __construct(
        public ?int $bmr,
        public ?int $tdee,
        public float $activityFactor,
        public int $adjustment,
        public ?int $target,
        public array $macros,
        public ?float $proteinPerKg,
        public array $fluids,
        public array $warnings,
        public array $errors,
    ) {}

    public function isValid(): bool
    {
        return $this->errors === [];
    }

    public function macroTotal(): int
    {
        return array_sum(array_column($this->macros, 'pct'));
    }
}
