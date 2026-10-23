<?php

namespace App\Data\Nutrition;

/** A person's body mass index, its class and their healthy weight range (BmiCalculator). */
final readonly class BmiAssessment
{
    /**
     * @param  ?string  $class  the class key (config nutrition_criteria.bmi); null when the
     *                          index is not classed for this person (pregnancy)
     * @param  ?string  $warning  why the chosen goal may not suit this class
     */
    public function __construct(
        public float $value,
        public ?string $class,
        public ?string $label,
        public ?string $clinical,
        public int $healthyMinKg,
        public int $healthyMaxKg,
        public ?string $warning = null,
    ) {}

    public function classified(): bool
    {
        return $this->class !== null;
    }
}
