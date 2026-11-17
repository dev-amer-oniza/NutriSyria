<?php

namespace App\Services\Nutrition\Criteria;

use App\Data\Nutrition\CriteriaSheet;
use App\Data\Nutrition\CriterionValue;
use App\Enums\CriterionSource;
use App\Enums\EnergyEquation;
use App\Enums\HealthCondition;
use App\Enums\Sex;
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
        $conditionKeys = array_values(array_intersect(array_keys($this->rules['conditions']), $profile['conditions'] ?? []));
        $conditions = array_values(array_intersect_key($this->rules['conditions'], array_flip($conditionKeys)));
        $conditionTemplates = $this->conditionTemplates($conditions);
        [$templateKey, $templateSource] = $this->chooseTemplate($profile, $conditionTemplates);
        $template = $this->rules['templates'][$templateKey];

        // A template judges with its shares only once verified, or when a nutritionist chose it;
        // until then the verified default shares judge, and the template is shown (decision 1a).
        $nutritionistChose = $templateSource === CriterionSource::Nutritionist;
        $templateVerified = $nutritionistChose || ! empty($template['refs']);
        $judging = $templateVerified ? $template : $this->rules['templates'][$this->rules['default_template']];

        $energy = $this->energy($profile, [$template, ...array_values($conditionTemplates)]);
        $context = ['profile' => $profile, 'kcal' => $energy['target'], 'template' => $judging, 'shown' => $templateVerified ? null : $template];

        // 1. Every criterion with a default rule (the macros from the judging template).
        $criteria = [];
        foreach ($this->rules['criteria'] as $key => $definition) {
            if ($definition['default'] !== null) {
                $criteria[$key] = $this->value($key, $definition['bound'], $definition['default'], $context, verified: ! empty($definition['refs']));
            }
        }

        // 2. The chosen template's own rules replace the defaults (kidneys: protein per kg).
        foreach ($template['rules'] ?? [] as $key => $rule) {
            $candidate = $this->value($key, $rule['bound'] ?? $this->rules['criteria'][$key]['bound'], $rule, $context,
                ' (قالب '.$template['label'].')', $nutritionistChose || ! empty($rule['refs']), 'قالب '.$template['label']);
            $criteria[$key] = $this->place($criteria[$key] ?? null, $candidate, replace: true);
        }

        // 3. From the other templates and the conditions, only ceilings and floors, the stricter winning.
        $limits = [];
        foreach ($conditionTemplates as $key => $other) {
            if ($key !== $templateKey) {
                $limits[] = [$other['rules'] ?? [], ' (قالب '.$other['label'].')', 'قالب '.$other['label']];
            }
        }
        foreach ($conditionKeys as $key) {
            $limits[] = [$this->rules['conditions'][$key]['criteria'] ?? [], ' (بسبب حالتك الصحية)', $this->conditionLabel($key)];
        }
        foreach ($limits as [$ruleSet, $why, $origin]) {
            foreach ($ruleSet as $key => $rule) {
                $bound = $rule['bound'] ?? $this->rules['criteria'][$key]['bound'];
                if ($bound !== 'target') {
                    $candidate = $this->value($key, $bound, $rule, $context, $why, ! empty($rule['refs']), $origin);
                    $criteria[$key] = $this->place($criteria[$key] ?? null, $candidate, replace: false);
                }
            }
        }

        // 4. A floor under a lower ceiling on the same nutrient goes, even a ceiling not verified
        //    yet: a kidney template's protein ceiling (0.6 g/kg) is never answered with "eat more
        //    protein" from the RDA.
        $ceilings = [];
        foreach ($criteria as $value) {
            foreach ([$value, $value->pending] as $one) {
                if ($one?->bound === 'max') {
                    $ceilings[] = $one;
                }
            }
        }
        foreach ($criteria as $key => $floor) {
            foreach ($ceilings as $ceiling) {
                if ($floor->bound === 'min' && $ceiling->nutrient === $floor->nutrient && $ceiling->value < $floor->value) {
                    unset($criteria[$key]);
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
                'verified' => $templateVerified,
                'judging' => $judging['label'],
            ],
            advice: array_values(array_unique(array_filter(array_column($sources, 'see_nutritionist')))),
            allergens: array_values(array_unique([...($profile['allergens'] ?? []), ...array_merge(...array_map(fn ($s) => $s['allergens'] ?? [], $sources))])),
            sensitiveDigestion: array_filter(
                $profile['conditions'] ?? [],
                fn ($c) => HealthCondition::tryFrom((string) $c)?->feelsLegumeFibre() ?? false,
            ) !== [],
            pendingConditions: $this->pendingConditions($conditionKeys, $nutritionistChose ? $templateKey : null),
            avoided: $this->avoided($sources),
        );
    }

    /**
     * Where a rule goes on the sheet. A verified rule judges; an unverified one never does: it
     * rides along as the judging value's pending rule when it is stricter or of another kind,
     * and stands alone (shown, not judged) when nothing verified covers the criterion.
     * $replace: the chosen template's own rule replaces the default instead of competing.
     */
    private function place(?CriterionValue $current, CriterionValue $candidate, bool $replace): CriterionValue
    {
        if ($current === null) {
            return $candidate;
        }
        if ($candidate->verified) {
            if (! $current->verified) {
                return $candidate->withPending($this->tighter($candidate, $current) ? $current : null);
            }
            $winner = $replace ? $candidate : $this->stricter($current, $candidate);

            return $winner->withPending($current->pending ?? $candidate->pending);
        }
        if (! $current->verified) {
            return $replace ? $candidate : $this->stricter($current, $candidate);
        }
        if (! $this->tighter($current, $candidate)) {
            return $current;
        }

        return $current->withPending($current->pending && ! $replace ? $this->stricter($current->pending, $candidate) : $candidate);
    }

    /** Whether $other asks more than $judging does: a lower ceiling, a higher floor, or another kind of limit. */
    private function tighter(CriterionValue $judging, CriterionValue $other): bool
    {
        return match (true) {
            $other->bound !== $judging->bound => true,
            $other->bound === 'max' => $other->value < $judging->value,
            $other->bound === 'min' => $other->value > $judging->value,
            default => false,
        };
    }

    /**
     * The conditions with rules not verified yet, by name, for "قيد التوثيق: حدود …". A template
     * a nutritionist chose counts as theirs, so its own rules are not pending.
     *
     * @param  list<string>  $conditionKeys
     * @return list<string>
     */
    private function pendingConditions(array $conditionKeys, ?string $chosenByNutritionist): array
    {
        $pending = [];
        foreach ($conditionKeys as $key) {
            $condition = $this->rules['conditions'][$key];
            $templateKey = $condition['template'] ?? null;
            $rules = [
                ...array_values($templateKey && $templateKey !== $chosenByNutritionist ? ($this->rules['templates'][$templateKey]['rules'] ?? []) : []),
                ...array_values($condition['criteria'] ?? []),
            ];
            if (array_filter($rules, fn (array $rule) => empty($rule['refs'])) !== []) {
                $pending[] = $this->conditionLabel($key);
            }
        }

        return array_values(array_unique($pending));
    }

    /**
     * The allergens the templates avoid, with why, for the reason a meal is unfit ("ومرض السيلياك
     * يُعالَج بتجنّبه تماماً"). The person's own allergies are said differently.
     *
     * @param  list<array>  $sources
     * @return array<string, string>
     */
    private function avoided(array $sources): array
    {
        $avoided = [];
        foreach ($sources as $source) {
            foreach ($source['allergens'] ?? [] as $allergen) {
                $avoided[$allergen] ??= $source['avoid_reason'] ?? 'ولا يناسب حالتك';
            }
        }

        return $avoided;
    }

    private function conditionLabel(string $key): string
    {
        return HealthCondition::tryFrom($key)?->label() ?? $key;
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
     * Mifflin-St Jeor resting energy, times the activity level, plus the goal's change, never
     * below the safe minimum. The change follows its reference (config 'goal_adjustment'):
     * no deficit under 'deficit_min_bmi' or when a template forbids one ('held'), and a
     * surplus without a guideline is only shown ('surplus'), never added to the target.
     *
     * @return array{bmr: int, tdee: int, adjustment: int, floored: bool, target: int, held: ?string, surplus: int}
     */
    private function energy(array $profile, array $templates): array
    {
        $bmr = EnergyEquation::Mifflin->bmr(Sex::from($profile['sex']), (int) $profile['age'], (float) $profile['height_cm'], (float) $profile['weight_kg']);
        $tdee = $bmr * (float) $this->rules['activity_factors'][$profile['activity']];

        $adjustment = (int) $this->rules['goal_adjustment'][$profile['goal']];
        $held = null;
        $surplus = 0;
        $noDeficit = collect($templates)->contains(fn (array $t) => $t['no_deficit'] ?? false);
        if ($adjustment < 0 && $noDeficit) {
            [$adjustment, $held] = [0, 'template'];
        } elseif ($adjustment < 0 && $this->bmi($profile) < (float) ($this->rules['deficit_min_bmi'] ?? 0)) {
            [$adjustment, $held] = [0, 'bmi'];
        }
        if ($adjustment > 0 && ! ($this->rules['surplus_judged'] ?? true)) {
            [$surplus, $adjustment] = [$adjustment, 0];
        }

        $minimum = (int) $this->rules['min_kcal'][$profile['sex']];
        $target = $tdee + $adjustment;

        return [
            'bmr' => (int) round($bmr),
            'tdee' => (int) round($tdee),
            'adjustment' => $adjustment,
            'floored' => $target < $minimum,
            'target' => (int) (round(max($target, $minimum) / 10) * 10),
            'held' => $held,
            'surplus' => $surplus,
        ];
    }

    /** Weight over height squared, to one decimal like BmiCalculator. */
    private function bmi(array $profile): float
    {
        $metres = (float) $profile['height_cm'] / 100;

        return $metres > 0 ? round((float) $profile['weight_kg'] / ($metres * $metres), 1) : 0.0;
    }

    private function value(string $key, string $bound, array $rule, array $context, string $why = '', bool $verified = false, ?string $origin = null): CriterionValue
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
            verified: $verified,
            origin: $origin,
        );
    }

    /** @return array{0: float, 1: string} the value and how it was reached */
    private function evaluate(array $rule, array $context): array
    {
        $kcal = $context['kcal'];
        $profile = $context['profile'];

        return match ($rule['type']) {
            'energy' => [$kcal, 'معادلة Mifflin-St Jeor مضروبة بمستوى نشاطك، ومعدّلة حسب هدفك'],
            'macro' => $this->macro($rule['macro'], $context['template'], $kcal, $context['shown'] ?? null),
            'per_kg' => $this->perKg($rule, $profile['weight_kg'], $profile['goal']),
            'energy_pct' => [$kcal * $rule['pct'] / 100 / $rule['kcal_per_g'], $rule['pct'].'% من سعراتك اليومية'],
            'per_1000' => [$kcal / 1000 * $rule['value'], $rule['value'].' غ لكل 1000 سعرة'],
            'fixed' => [(float) $rule['value'], 'قيمة ثابتة موصى بها'],
            'by_sex' => [(float) $rule[$profile['sex']], 'قيمة موصى بها حسب الجنس'],
            default => throw new InvalidArgumentException("Unknown criterion rule [{$rule['type']}]."),
        };
    }

    /** @return array{0: float, 1: string} */
    private function macro(string $macro, array $template, int $kcal, ?array $shown = null): array
    {
        $pct = $template['macros'][$macro];
        $basis = $pct.'% من سعراتك حسب قالب '.$template['label'];
        if ($shown) {
            $basis .= '، لأن قالب '.$shown['label'].' ('.$shown['macros'][$macro].'%) قيد التوثيق';
        }

        return [$kcal * $pct / 100 / $this->rules['kcal_per_g'][$macro], $basis];
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
