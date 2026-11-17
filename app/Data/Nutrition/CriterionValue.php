<?php

namespace App\Data\Nutrition;

use App\Enums\CriterionSource;

/**
 * One criterion's value for one person, with where it comes from and why.
 *
 * Only a verified value judges a meal (the owner's plan, step 2): one backed by references
 * (config/nutrition_references.php), or set by the person, their doctor or nutritionist.
 * An unverified rule stricter than the judging one rides along as $pending, shown only.
 */
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
        /** strict | tolerance | guidance | shortfall: how far going past it can drop a verdict */
        public string $kind = 'guidance',
        public bool $verified = true,
        /** Where the rule comes from, when not the general needs: "عدم تحمّل الدسم", "قالب السكري". */
        public ?string $origin = null,
        /** A stricter rule not verified yet: shown as information, never judged. */
        public ?CriterionValue $pending = null,
    ) {}

    public function withPending(?CriterionValue $pending): self
    {
        return new self(
            $this->key, $this->label, $this->unit, $this->bound, $this->scope, $this->nutrient, $this->value,
            $this->source, $this->basis, $this->note, $this->defaultValue, $this->kind, $this->verified, $this->origin, $pending,
        );
    }

    /** The part of a daily value that one meal may take. */
    public function perMeal(float $mealShare): float
    {
        return $this->scope === 'meal' ? $this->value : $this->value * $mealShare;
    }

    /** A value a person, their doctor or nutritionist set: it judges, and replaces any pending rule. */
    public function overriddenBy(CriterionSource $source, float $value, ?string $note): self
    {
        return new self(
            $this->key, $this->label, $this->unit, $this->bound, $this->scope, $this->nutrient,
            $value, $source, $source->label(), $note ?? $this->note, $this->defaultValue ?? $this->value,
            $source->kind($this->bound, $this->kind), true, null, null,
        );
    }
}
