<?php

namespace App\Data\Nutrition;

use App\Enums\CriterionSource;

/** Everything a person's meals are judged against, plus how the calories were reached. */
final readonly class CriteriaSheet
{
    /**
     * @param  array<string, CriterionValue>  $criteria  keyed by criterion key
     * @param  array{bmr: int, tdee: int, adjustment: int, floored: bool}  $energy
     * @param  array{key: string, label: string, description: string, macros: array{carbs: int, protein: int, fat: int}, source: CriterionSource, verified?: bool, judging?: string, brought_by?: list<string>, note?: string}  $template
     * @param  list<string>  $advice  reasons to see a nutritionist
     * @param  list<string>  $allergens  App\Enums\Allergen values to avoid
     * @param  array{name: string, time: ?string, share: float, kcal: int, targets: array<string, float>}|null  $meal
     *                                                                                                                 the nutritionist's plan lunch that restaurant dishes are judged against (else meal_share of the day)
     */
    public function __construct(
        public array $criteria,
        public array $energy,
        public float $mealShare,
        public array $template,
        public array $advice = [],
        public array $allergens = [],
        public ?array $meal = null,
        /** A condition whose digestion may suffer from much legume fibre at once (HealthCondition::feelsLegumeFibre). */
        public bool $sensitiveDigestion = false,
        /** @var list<string> the person's conditions with rules not verified yet, by name */
        public array $pendingConditions = [],
        /** @var array<string, string> allergens a template avoids => why ("ومرض السيلياك يُعالَج بتجنّبه تماماً") */
        public array $avoided = [],
        /** @var list<string> the person's condition keys, for the card "من أين هذا الحد؟" opens */
        public array $conditions = [],
    ) {}

    private function copy(array $criteria, float $mealShare, array $template, ?array $meal): self
    {
        return new self($criteria, $this->energy, $mealShare, $template, $this->advice, $this->allergens, $meal, $this->sensitiveDigestion, $this->pendingConditions, $this->avoided, $this->conditions);
    }

    public function get(string $key): ?CriterionValue
    {
        return $this->criteria[$key] ?? null;
    }

    /** The note left with the chosen template (the nutritionist's "why"). */
    public function withTemplateNote(string $note): self
    {
        return $this->copy($this->criteria, $this->mealShare, [...$this->template, 'note' => $note], $this->meal);
    }

    public function with(CriterionValue $value): self
    {
        return $this->copy([...$this->criteria, $value->key => $value], $this->mealShare, $this->template, $this->meal);
    }

    /** One restaurant dish is compared with this meal from the nutritionist's plan (phase 3, 1a). */
    public function withMeal(array $meal): self
    {
        return $this->copy($this->criteria, $meal['share'], $this->template, $meal);
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
