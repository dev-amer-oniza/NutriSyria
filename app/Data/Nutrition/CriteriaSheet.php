<?php

namespace App\Data\Nutrition;

use App\Enums\CriterionSource;

/** Everything a person's meals are judged against, plus how the calories were reached. */
final readonly class CriteriaSheet
{
    /**
     * @param  array<string, CriterionValue>  $criteria  keyed by criterion key
     * @param  array{bmr: int, tdee: int, adjustment: int, floored: bool}  $energy
     * @param  array{key: string, label: string, description: string, macros: array{carbs: int, protein: int, fat: int}, source: CriterionSource, note?: string}  $template
     * @param  list<string>  $advice  reasons to see a nutritionist
     * @param  list<string>  $allergens  App\Enums\Allergen values to avoid
     * @param  array{name: string, time: ?string, share: float, kcal: int, targets: array<string, float>}|null  $meal
     *         the nutritionist's plan lunch that restaurant dishes are judged against (else meal_share of the day)
     */
    public function __construct(
        public array $criteria,
        public array $energy,
        public float $mealShare,
        public array $template,
        public array $advice = [],
        public array $allergens = [],
        public ?array $meal = null,
    ) {}

    public function get(string $key): ?CriterionValue
    {
        return $this->criteria[$key] ?? null;
    }

    /** The note left with the chosen template (the nutritionist's "why"). */
    public function withTemplateNote(string $note): self
    {
        return new self($this->criteria, $this->energy, $this->mealShare, [...$this->template, 'note' => $note], $this->advice, $this->allergens, $this->meal);
    }

    public function with(CriterionValue $value): self
    {
        return new self([...$this->criteria, $value->key => $value], $this->energy, $this->mealShare, $this->template, $this->advice, $this->allergens, $this->meal);
    }

    /** One restaurant dish is compared with this meal from the nutritionist's plan (phase 3, 1a). */
    public function withMeal(array $meal): self
    {
        return new self($this->criteria, $this->energy, $meal['share'], $this->template, $this->advice, $this->allergens, $meal);
    }

    /**
     * What one meal may take of a criterion: the plan meal's own calories and grams when the
     * nutritionist set them (1a), else the meal's share of the daily value, or the meal limit.
     */
    public function perMeal(CriterionValue $criterion): float
    {
        if ($criterion->scope === 'day' && isset($this->meal['targets'][$criterion->key])) {
            return (float) $this->meal['targets'][$criterion->key];
        }

        return $criterion->perMeal($this->mealShare);
    }
}
