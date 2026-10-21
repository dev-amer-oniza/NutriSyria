<?php

namespace App\Data\Nutrition;

use App\Enums\CriterionSource;

/** Everything a person's meals are judged against, plus how the calories were reached. */
final readonly class CriteriaSheet
{
    /**
     * @param  array<string, CriterionValue>  $criteria  keyed by criterion key
     * @param  array{bmr: int, tdee: int, adjustment: int, floored: bool}  $energy
     * @param  array{key: string, label: string, description: string, macros: array{carbs: int, protein: int, fat: int}, source: CriterionSource}  $template
     * @param  list<string>  $advice  reasons to see a nutritionist
     * @param  list<string>  $allergens  App\Enums\Allergen values to avoid
     */
    public function __construct(
        public array $criteria,
        public array $energy,
        public float $mealShare,
        public array $template,
        public array $advice = [],
        public array $allergens = [],
    ) {}

    public function get(string $key): ?CriterionValue
    {
        return $this->criteria[$key] ?? null;
    }

    public function with(CriterionValue $value): self
    {
        return new self([...$this->criteria, $value->key => $value], $this->energy, $this->mealShare, $this->template, $this->advice, $this->allergens);
    }
}
