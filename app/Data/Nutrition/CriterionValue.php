<?php

namespace App\Data\Nutrition;

use App\Enums\CriterionSource;

/** One criterion's value for one person, with where it comes from and why. */
final readonly class CriterionValue
{
    public function __construct(
        public string $key,
        public string $label,
        public string $unit,
        /** min | max | target */
        public string $bound,
        /** day | meal (a meal value is not scaled by the meal share) */
        public string $scope,
        public string $nutrient,
        public float $value,
        public CriterionSource $source,
        /** How the value was reached, in plain Arabic. */
        public string $basis,
        public ?string $note = null,
        /** The value before a person or nutritionist changed it. */
        public ?float $defaultValue = null,
        /** strict | guidance | shortfall: how far going past it can drop a verdict */
        public string $kind = 'guidance',
    ) {}

    /** The part of a daily value that one meal may take. */
    public function perMeal(float $mealShare): float
    {
        return $this->scope === 'meal' ? $this->value : $this->value * $mealShare;
    }

    /** A value the person's nutritionist set. */
    public function overriddenBy(CriterionSource $source, float $value, ?string $note): self
    {
        return new self(
            $this->key, $this->label, $this->unit, $this->bound, $this->scope, $this->nutrient,
            $value, $source, $source->label(), $note ?? $this->note, $this->defaultValue ?? $this->value,
            $source->kind($this->bound, $this->kind),
        );
    }
}
