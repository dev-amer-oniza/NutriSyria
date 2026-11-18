<?php

namespace App\Data\Nutrition;

/** A person's body mass index, its class and their healthy weight range (BmiCalculator). */
final readonly class BmiAssessment
{
    /**
     * @param  string  $class  the class key (config nutrition_criteria.bmi)
     * @param  ?string  $warning  a gentle word about the chosen goal for this class
     */
    public function __construct(
        public float $value,
        public string $class,
        public string $label,
        public string $clinical,
        public int $healthyMinKg,
        public int $healthyMaxKg,
        public ?string $warning = null,
    ) {}
}
