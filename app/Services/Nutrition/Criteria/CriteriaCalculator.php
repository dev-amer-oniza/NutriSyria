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
 * goal (lose|maintain|gain), conditions (list), allergens (list), and optionally template
 * (a template chosen by the person or their nutritionist) with template_source.
 */
final class CriteriaCalculator
{
    public function __construct(private readonly array $rules) {}

    public function calculate(array $profile): CriteriaSheet
    {
        $conditions = array_values(array_intersect_key($this->rules['conditions'], array_flip($profile['conditions'] ?? [])));
        $conditionTemplates = $this->conditionTemplates($conditions);
        [$templateKey, $templateSource] = $this->chooseTemplate($profile, $conditionTemplates);
        $template = $this->rules['templates'][$templateKey];

        $energy = $this->energy($profile, [$template, ...array_values($conditionTemplates)]);
        $context = ['profile' => $profile, 'kcal' => $energy['target'], 'template' => $template];

        // 1. Every criterion with a default rule (the macros from the chosen template).
        $criteria = [];
        foreach ($this->rules['criteria'] as $key => $definition) {
            if ($definition['default'] !== null) {
                $criteria[$key] = $this->value($key, $definition['bound'], $definition['default'], $context);
            }
        }

        // 2. The chosen template's own rules replace the defaults (kidneys: protein per kg).
        foreach ($template['rules'] ?? [] as $key => $rule) {
            $criteria[$key] = $this->value($key, $rule['bound'] ?? $this->rules['criteria'][$key]['bound'], $rule, $context, ' (قالب '.$template['label'].')');
        }

        // 3. From the other templates and the conditions, only ceilings and floors, the stricter winning.
        $limits = [];
        foreach ($conditionTemplates as $key => $other) {
            if ($key !== $templateKey) {
                $limits[] = [$other['rules'] ?? [], ' (قالب '.$other['label'].')'];
            }
        }
        foreach ($conditions as $condition) {
            $limits[] = [$condition['criteria'] ?? [], ' (بسبب حالتك الصحية)'];
        }
        foreach ($limits as [$ruleSet, $why]) {
            foreach ($ruleSet as $key => $rule) {
                $bound = $rule['bound'] ?? $this->rules['criteria'][$key]['bound'];
                if ($bound !== 'target') {
                    $criteria[$key] = $this->stricter($criteria[$key] ?? null, $this->value($key, $bound, $rule, $context, $why));
                }
            }
        }

        $sources = [$template, ...array_values($conditionTemplates), ...$conditions];

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
            advice: array_values(array_unique(array_filter(array_column($sources, 'see_nutritionist')))),
            allergens: array_values(array_unique([...($profile['allergens'] ?? []), ...array_merge(...array_map(fn ($s) => $s['allergens'] ?? [], $sources))])),
        );
    }

    /** @return array<string, array> the templates the person's conditions bring, highest priority first */
    private function conditionTemplates(array $conditions): array
    {
        $keys = array_unique(array_filter(array_column($conditions, 'template')));
        $ordered = array_values(array_intersect($this->rules['template_priority'], $keys));

        return array_combine($ordered, array_map(fn ($k) => $this->rules['templates'][$k], $ordered));
    }

    /** @return array{0: string, 1: CriterionSource} */
    private function chooseTemplate(array $profile, array $conditionTemplates): array
    {
        $chosen = $profile['template'] ?? null;

        if ($chosen && isset($this->rules['templates'][$chosen])) {
            return [$chosen, $profile['template_source'] ?? CriterionSource::User];
        }

        return [array_key_first($conditionTemplates) ?? $this->rules['default_template'], CriterionSource::Default];
    }

    /**
     * Mifflin-St Jeor resting energy, times the activity level, plus the goal's change
     * (none when a template forbids a deficit), never below the safe minimum.
     *
     * @return array{bmr: int, tdee: int, adjustment: int, floored: bool, target: int}
     */
    private function energy(array $profile, array $templates): array
    {
        $female = $profile['sex'] === 'female';
        $bmr = 10 * $profile['weight_kg'] + 6.25 * $profile['height_cm'] - 5 * $profile['age'] + ($female ? -161 : 5);
        $tdee = $bmr * (float) $this->rules['activity_factors'][$profile['activity']];

        $adjustment = (int) $this->rules['goal_adjustment'][$profile['goal']];
        $noDeficit = collect($templates)->contains(fn (array $t) => $t['no_deficit'] ?? false);
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
            'per_kg' => $this->perKg($rule, $profile['weight_kg'], $profile['goal']),
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

    /** @return array{0: float, 1: string} */
    private function perKg(array $rule, float $weight, string $goal): array
    {
        $perKg = (float) ($rule['by_goal'][$goal] ?? $rule['value']);

        return [$weight * $perKg, $perKg.' غ لكل كغ من وزنك'];
    }

    /**
     * A ceiling or floor from another template or a condition: the lower ceiling or the
     * higher floor wins. It never changes the kind of a limit set by the chosen template.
     */
    private function stricter(?CriterionValue $current, CriterionValue $candidate): CriterionValue
    {
        if ($current === null) {
            return $candidate;
        }
        if ($current->bound !== $candidate->bound) {
            return $current;
        }

        return match ($candidate->bound) {
            'max' => $candidate->value < $current->value ? $candidate : $current,
            'min' => $candidate->value > $current->value ? $candidate : $current,
            default => $current,
        };
    }
}
