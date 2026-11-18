<?php

namespace App\Services\Nutrition\Criteria;

use App\Data\Nutrition\CriteriaSheet;
use App\Data\Nutrition\CriterionValue;
use App\Enums\CriterionSource;
use App\Enums\EnergyEquation;
use App\Enums\Sex;
use InvalidArgumentException;

/**
 * Works out a person's default criteria from their profile and the rules in
 * config/nutrition_criteria.php. Pure: no database, arrays in and a CriteriaSheet out.
 *
 * Profile keys: sex (male|female), age, height_cm, weight_kg, activity (level key),
 * goal (lose|maintain|gain), allergens (list), and optionally template (one chosen by the
 * person's nutritionist) with template_source.
 */
final class CriteriaCalculator
{
    public function __construct(private readonly array $rules) {}

    public function calculate(array $profile): CriteriaSheet
    {
        $chosen = $profile['template'] ?? null;
        [$templateKey, $templateSource] = $chosen && isset($this->rules['templates'][$chosen])
            ? [$chosen, $profile['template_source'] ?? CriterionSource::Nutritionist]
            : [$this->rules['default_template'], CriterionSource::Default];
        $template = $this->rules['templates'][$templateKey];

        $energy = $this->energy($profile);
        $context = ['profile' => $profile, 'kcal' => $energy['target'], 'template' => $template];

        // Every criterion with a default rule (the macros from the template), then the template's own rules.
        $criteria = [];
        foreach ($this->rules['criteria'] as $key => $definition) {
            if ($definition['default'] !== null) {
                $criteria[$key] = $this->value($key, $definition['bound'], $definition['default'], $context);
            }
        }
        foreach ($template['rules'] ?? [] as $key => $rule) {
            $criteria[$key] = $this->value($key, $rule['bound'] ?? $this->rules['criteria'][$key]['bound'], $rule, $context, ' (قالب '.$template['label'].')');
        }

        // A floor under a lower ceiling on the same nutrient goes: never "eat more" past a ceiling.
        foreach ($criteria as $key => $floor) {
            foreach ($criteria as $ceiling) {
                if ($floor->bound === 'min' && $ceiling->bound === 'max' && $ceiling->nutrient === $floor->nutrient && $ceiling->value < $floor->value) {
                    unset($criteria[$key]);
                }
            }
        }

        return new CriteriaSheet(
            criteria: $criteria,
            energy: array_diff_key($energy, ['target' => 0]),
            mealShare: (float) $this->rules['meal_share'],
            template: [
                'key' => $templateKey,
                'label' => $template['label'],
                'description' => $template['description'],
                'macros' => $template['macros'],
                'source' => $templateSource,
            ],
            allergens: array_values(array_unique($profile['allergens'] ?? [])),
        );
    }

    /**
     * Mifflin-St Jeor resting energy, times the activity level, plus the goal's change, never
     * below the safe minimum ('floored').
     *
     * @return array{bmr: int, tdee: int, adjustment: int, floored: bool, target: int}
     */
    private function energy(array $profile): array
    {
        $bmr = EnergyEquation::Mifflin->bmr(Sex::from($profile['sex']), (int) $profile['age'], (float) $profile['height_cm'], (float) $profile['weight_kg']);
        $tdee = $bmr * (float) $this->rules['activity_factors'][$profile['activity']];
        $adjustment = (int) $this->rules['goal_adjustment'][$profile['goal']];

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

    private function value(string $key, string $bound, array $rule, array $context, string $why = ''): CriterionValue
    {
        $definition = $this->rules['criteria'][$key];
        [$value, $basis] = $this->evaluate($rule, $context);

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
            basis: $basis.$why,
            note: $definition['note'] ?? null,
            kind: $bound === 'min' ? 'shortfall' : ($definition['kind'] ?? 'guidance'),
        );
    }

    /** @return array{0: float, 1: string} the value and how it was reached */
    private function evaluate(array $rule, array $context): array
    {
        $kcal = $context['kcal'];
        $profile = $context['profile'];

        return match ($rule['type']) {
            'energy' => [$kcal, 'معادلة Mifflin-St Jeor مضروبة بمستوى نشاطك، ومعدّلة حسب هدفك'],
            'macro' => $this->macro($rule['macro'], $context['template'], $kcal),
            'per_kg' => [(float) $profile['weight_kg'] * (float) $rule['value'], $rule['value'].' غ لكل كغ من وزنك'],
            'energy_pct' => [$kcal * $rule['pct'] / 100 / $rule['kcal_per_g'], $rule['pct'].'% من سعراتك اليومية'],
            'per_1000' => [$kcal / 1000 * $rule['value'], $rule['value'].' غ لكل 1000 سعرة'],
            'fixed' => [(float) $rule['value'], 'قيمة ثابتة موصى بها'],
            'by_sex' => [(float) $rule[$profile['sex']], 'قيمة موصى بها حسب الجنس'],
            default => throw new InvalidArgumentException("Unknown criterion rule [{$rule['type']}]."),
        };
    }

    /** @return array{0: float, 1: string} */
    private function macro(string $macro, array $template, int $kcal): array
    {
        $pct = $template['macros'][$macro];

        return [$kcal * $pct / 100 / $this->rules['kcal_per_g'][$macro], $pct.'% من سعراتك حسب قالب '.$template['label']];
    }
}
