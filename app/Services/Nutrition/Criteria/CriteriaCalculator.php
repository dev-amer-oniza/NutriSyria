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
 * Profile keys: sex (male|female), age, height_cm, weight_kg, daily_life (App\Enums\DailyLife),
 * work_days (nullable), exercises (list of {type, times, minutes}), goal (lose|maintain|gain),
 * allergens (list), and optionally template (one chosen by the person's nutritionist) with
 * template_source.
 *
 * The template comes from the answers (the owner's decision): the nutritionist's choice, else
 * 'sport' for training on sport_days a week or more, else the goal's template.
 */
final class CriteriaCalculator
{
    public function __construct(private readonly array $rules) {}

    public function calculate(array $profile): CriteriaSheet
    {
        [$templateKey, $templateSource] = $this->template($profile);
        $template = $this->rules['templates'][$templateKey];

        $energy = $this->energy($profile);
        $macros = $this->macros($template, $profile, $energy['target']);
        $context = ['profile' => $profile, 'kcal' => $energy['target'], 'macros' => $macros, 'template' => $template];

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
                // The shares the grams make, in % of the calories (the nutritionist's plan starts from them).
                'macros' => $macros['pct'],
                // For people who train: their level and its carbohydrate per kg (null otherwise).
                'sport' => $macros['sport'],
                'source' => $templateSource,
            ],
            allergens: array_values(array_unique($profile['allergens'] ?? [])),
        );
    }

    /** @return array{0: string, 1: CriterionSource} */
    private function template(array $profile): array
    {
        $chosen = $profile['template'] ?? null;
        if ($chosen && isset($this->rules['templates'][$chosen])) {
            return [$chosen, $profile['template_source'] ?? CriterionSource::Nutritionist];
        }
        if (EnergyCalculator::trainingDays($profile['exercises'] ?? []) >= (int) $this->rules['sport_days']) {
            return ['sport', CriterionSource::Default];
        }

        return [$this->rules['goal_templates'][$profile['goal']] ?? $this->rules['default_template'], CriterionSource::Default];
    }

    /**
     * The day's energy (EnergyCalculator), and the goal's change kept safe: a deficit never
     * goes under the minimum ('floored'), and when the day itself is at or under it, there is
     * no deficit at all ('held': more activity or a nutritionist, not less food).
     *
     * @return array{bmr: int, factor: float, base: int, exercise: int, tdee: int, pal: float, unusual: bool, adjustment: int, floored: bool, held: bool, target: int}
     */
    private function energy(array $profile): array
    {
        $weight = (float) $profile['weight_kg'];
        $bmr = EnergyEquation::Mifflin->bmr(Sex::from($profile['sex']), (int) $profile['age'], (float) $profile['height_cm'], $weight);
        $day = (new EnergyCalculator($this->rules))->day($bmr, $weight, $profile['daily_life'], $profile['work_days'] ?? null, $profile['exercises'] ?? []);
        $tdee = (int) round($day['tdee']);

        $adjustment = (int) $this->rules['goal_adjustment'][$profile['goal']];
        $minimum = (int) $this->rules['min_kcal'][$profile['sex']];
        [$floored, $held] = [false, false];
        if ($adjustment < 0 && $tdee <= $minimum) {
            [$adjustment, $held] = [0, true];
        } elseif ($adjustment < 0 && $tdee + $adjustment < $minimum) {
            [$adjustment, $floored] = [$minimum - $tdee, true];
        }

        return [
            'bmr' => (int) round($bmr),
            'factor' => round($day['factor'], 3),
            'base' => (int) round($day['base']),
            'exercise' => (int) round($day['exercise']),
            'tdee' => $tdee,
            'pal' => round($day['pal'], 2),
            'unusual' => $day['unusual'],
            'adjustment' => $adjustment,
            'floored' => $floored,
            'held' => $held,
            'target' => $tdee + $adjustment,
        ];
    }

    /**
     * Protein first, in grams per kg (never past protein_max_pct of the calories); fat at
     * fat_pct, coming down fat_step at a time to fat_min_pct while the carbohydrate is under
     * carbs_min_pct; carbohydrate the rest (config macro_rule). A template for people who train
     * ('sport_carbs') also needs the least carbohydrate per kg of its training level (acsm_2016):
     * the fat keeps coming down for it, never under fat_min_pct, and a shortfall is reported.
     *
     * @return array{g: array{carbs: float, protein: float, fat: float}, pct: array{carbs: int, protein: int, fat: int}, protein_per_kg: float, sport: ?array{level: string, label: string, hours: float, range: array{0: int|float, 1: int|float}, per_kg: float, short: bool}}
     */
    private function macros(array $template, array $profile, int $kcal): array
    {
        $rule = $this->rules['macro_rule'];
        $perG = $this->rules['kcal_per_g'];
        $perKg = $template['protein_per_kg'];
        $perKg = (float) (is_array($perKg) ? ($perKg[$profile['goal']] ?? reset($perKg)) : $perKg);

        $protein = min($perKg * (float) $profile['weight_kg'], $kcal * $rule['protein_max_pct'] / 100 / $perG['protein']);
        $fatPct = (int) $rule['fat_pct'];
        $carbs = fn (int $fatPct) => ($kcal - $protein * $perG['protein'] - $kcal * $fatPct / 100) / $perG['carbs'];
        $weight = (float) $profile['weight_kg'];
        $level = ($template['sport_carbs'] ?? false) ? $this->sportLevel($profile['exercises'] ?? []) : null;
        $carbsFloor = max($kcal * $rule['carbs_min_pct'] / 100 / $perG['carbs'], $level ? $level['g_per_kg'][0] * $weight : 0);
        while ($fatPct > $rule['fat_min_pct'] && $carbs($fatPct) < $carbsFloor) {
            $fatPct = max((int) $rule['fat_min_pct'], $fatPct - (int) $rule['fat_step']);
        }

        $grams = ['carbs' => max(0.0, $carbs($fatPct)), 'protein' => $protein, 'fat' => $kcal * $fatPct / 100 / $perG['fat']];
        $pct = fn (string $m) => $kcal > 0 ? (int) round($grams[$m] * $perG[$m] / $kcal * 100) : 0;

        $perKgCarbs = round($grams['carbs'] / $weight, 1);

        return [
            'g' => $grams,
            'pct' => ['carbs' => $pct('carbs'), 'protein' => $pct('protein'), 'fat' => $pct('fat')],
            'protein_per_kg' => $perKg,
            'sport' => $level ? [
                'level' => $level['key'],
                'label' => $level['label'],
                'hours' => round(EnergyCalculator::dailyHours($profile['exercises'] ?? []), 2),
                'range' => $level['g_per_kg'],
                'per_kg' => $perKgCarbs,
                'short' => $perKgCarbs < $level['g_per_kg'][0],
            ] : null,
        ];
    }

    /**
     * The training level from the hours a day on average (config sport_carbs, decision 3a).
     *
     * @return array{key: string, label: string, below_hours: int|float|null, g_per_kg: array{0: int|float, 1: int|float}}
     */
    private function sportLevel(array $exercises): array
    {
        $hours = EnergyCalculator::dailyHours($exercises);
        $levels = $this->rules['sport_carbs']['levels'];
        foreach ($levels as $level) {
            if ($level['below_hours'] === null || $hours < $level['below_hours']) {
                return $level;
            }
        }

        return end($levels);
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
            'energy' => [$kcal, 'معادلة Mifflin-St Jeor مضروبة بمعامل نهارك، مع تمرينك، ومعدّلة حسب هدفك'],
            'macro' => $this->macro($rule['macro'], $context),
            'per_kg' => [(float) $profile['weight_kg'] * (float) $rule['value'], $rule['value'].' غ لكل كغ من وزنك'],
            'energy_pct' => [$kcal * $rule['pct'] / 100 / $rule['kcal_per_g'], $rule['pct'].'% من سعراتك اليومية'],
            'per_1000' => [$kcal / 1000 * $rule['value'], $rule['value'].' غ لكل 1000 سعرة'],
            'fixed' => [(float) $rule['value'], 'قيمة ثابتة موصى بها'],
            'by_sex' => [(float) $rule[$profile['sex']], 'قيمة موصى بها حسب الجنس'],
            default => throw new InvalidArgumentException("Unknown criterion rule [{$rule['type']}]."),
        };
    }

    /** @return array{0: float, 1: string} */
    private function macro(string $macro, array $context): array
    {
        $grams = $context['macros']['g'][$macro];

        return [$grams, match ($macro) {
            'protein' => $context['macros']['protein_per_kg'].' غ لكل كغ من وزنك (قالب '.$context['template']['label'].')',
            'fat' => $context['macros']['pct']['fat'].'% من سعراتك',
            default => 'باقي السعرات بعد البروتين والدهون',
        }];
    }
}
