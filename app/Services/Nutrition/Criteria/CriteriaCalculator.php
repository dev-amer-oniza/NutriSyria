<?php

namespace App\Services\Nutrition\Criteria;

use App\Data\Nutrition\CriteriaSheet;
use App\Data\Nutrition\CriterionValue;
use App\Enums\CriterionSource;
use InvalidArgumentException;

/**
 * Works out a person's default criteria from their profile and the rules in
 * config/nutrition_criteria.php. Pure: no database, arrays in and a CriteriaSheet out.
 *
 * Profile keys: sex (male|female), age, height_cm, weight_kg, activity (level key),
 * goal (lose|maintain|gain), conditions (list), allergens (list).
 */
final class CriteriaCalculator
{
    public function __construct(private readonly array $rules) {}

    public function calculate(array $profile): CriteriaSheet
    {
        $conditions = array_values(array_intersect_key($this->rules['conditions'], array_flip($profile['conditions'] ?? [])));
        $energy = $this->energy($profile, $conditions);
        $goal = $profile['goal'];

        $criteria = [];
        foreach ($this->rules['criteria'] as $key => $definition) {
            if ($definition['default'] === null) {
                continue;
            }
            [$value, $basis] = $this->evaluate($definition['default'], $profile, $energy['target'], $goal);
            $criteria[$key] = $this->value($key, $definition, $definition['bound'], $value, $basis);
        }

        $advice = [];
        foreach ($profile['conditions'] ?? [] as $conditionKey) {
            $condition = $this->rules['conditions'][$conditionKey] ?? null;
            if ($condition === null) {
                continue;
            }
            foreach ($condition['criteria'] as $key => $rule) {
                $definition = $this->rules['criteria'][$key];
                $bound = $rule['bound'] ?? $definition['bound'];
                [$value, $basis] = $this->evaluate($rule, $profile, $energy['target'], $goal);
                $candidate = $this->value($key, $definition, $bound, $value, $basis.' (بسبب حالتك الصحية)');
                $criteria[$key] = $this->stricter($criteria[$key] ?? null, $candidate);
            }
            if (isset($condition['see_nutritionist'])) {
                $advice[] = $condition['see_nutritionist'];
            }
        }

        return new CriteriaSheet(
            criteria: $criteria,
            energy: array_diff_key($energy, ['target' => 0]),
            mealShare: (float) $this->rules['meal_share'],
            advice: $advice,
            allergens: array_values($profile['allergens'] ?? []),
        );
    }

    /**
     * Mifflin-St Jeor resting energy, times the activity level, plus the goal's change
     * (none while pregnant or breastfeeding), never below the safe minimum.
     *
     * @return array{bmr: int, tdee: int, adjustment: int, floored: bool, target: int}
     */
    private function energy(array $profile, array $conditions): array
    {
        $female = $profile['sex'] === 'female';
        $bmr = 10 * $profile['weight_kg'] + 6.25 * $profile['height_cm'] - 5 * $profile['age'] + ($female ? -161 : 5);
        $tdee = $bmr * (float) $this->rules['activity_factors'][$profile['activity']];

        $adjustment = (int) $this->rules['goal_adjustment'][$profile['goal']];
        $noDeficit = collect($conditions)->contains(fn (array $c) => $c['no_deficit'] ?? false);
        if ($noDeficit && $adjustment < 0) {
            $adjustment = 0;
        }

        $minimum = (int) $this->rules['min_kcal'][$profile['sex']];
        $target = $tdee + $adjustment;

        return [
            'bmr' => (int) round($bmr),
            'tdee' => (int) round($tdee),
            'adjustment' => $adjustment,
            'floored' => $target < $minimum,
            'target' => (int) (round(max($target, $minimum) / 10) * 10),
        ];
    }

    /** @return array{0: float, 1: string} the value and how it was reached */
    private function evaluate(array $rule, array $profile, int $kcal, string $goal): array
    {
        return match ($rule['type']) {
            'energy' => [$kcal, 'معادلة Mifflin-St Jeor مضروبة بمستوى نشاطك، ومعدّلة حسب هدفك'],
            'per_kg' => $this->perKg($rule, $profile['weight_kg'], $goal),
            'energy_pct' => [round($kcal * $rule['pct'] / 100 / $rule['kcal_per_g']), $rule['pct'].'% من سعراتك اليومية'],
            'per_1000' => [round($kcal / 1000 * $rule['value']), $rule['value'].' غ لكل 1000 سعرة'],
            'fixed' => [(float) $rule['value'], 'قيمة ثابتة موصى بها'],
            'by_sex' => [(float) $rule[$profile['sex']], 'قيمة موصى بها حسب الجنس'],
            default => throw new InvalidArgumentException("Unknown criterion rule [{$rule['type']}]."),
        };
    }

    /** @return array{0: float, 1: string} */
    private function perKg(array $rule, float $weight, string $goal): array
    {
        $perKg = (float) ($rule['by_goal'][$goal] ?? $rule['value']);

        return [round($weight * $perKg), $perKg.' غ لكل كغ من وزنك'];
    }

    private function value(string $key, array $definition, string $bound, float $value, string $basis): CriterionValue
    {
        return new CriterionValue(
            key: $key,
            label: $definition['label'],
            unit: $definition['unit'],
            bound: $bound,
            scope: $definition['scope'],
            nutrient: $definition['nutrient'],
            // Large milligram amounts (sodium 2000) to the nearest ten; small ones (iron 18) as they are.
            value: $definition['unit'] === 'ملغ' && $value >= 100 ? round($value / 10) * 10 : round($value),
            source: CriterionSource::Default,
            basis: $basis,
            note: $definition['note'] ?? null,
        );
    }

    /**
     * Two rules for one criterion: the lower ceiling or the higher floor wins. A condition
     * that changes the kind of limit (the kidneys turn protein into a ceiling) replaces it.
     */
    private function stricter(?CriterionValue $current, CriterionValue $candidate): CriterionValue
    {
        if ($current === null || $current->source !== CriterionSource::Default || $current->bound !== $candidate->bound) {
            return $candidate;
        }

        return match ($candidate->bound) {
            'max' => $candidate->value < $current->value ? $candidate : $current,
            'min' => $candidate->value > $current->value ? $candidate : $current,
            default => $candidate,
        };
    }
}
