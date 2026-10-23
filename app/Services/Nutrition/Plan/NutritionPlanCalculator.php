<?php

namespace App\Services\Nutrition\Plan;

use App\Data\Nutrition\NutritionPlanResult;
use App\Enums\EnergyEquation;
use App\Enums\Sex;

/**
 * Works out a nutritionist's plan for one client (pure: arrays in, a result out). The steps the
 * nutritionist asked for: resting energy by the chosen equation, times the activity factor,
 * plus the chosen change; the calories shared between carbohydrate, protein and fat by %;
 * and the fluids. Rules and limits are in config/nutrition_criteria.php (plan).
 *
 * Profile keys: sex, age, height_cm, weight_kg, conditions (list).
 * Plan keys: equation, body_fat_pct (nullable), activity (level key), adjustment (kcal),
 * carbs_pct, protein_pct, fat_pct, fluids_ml (nullable), and optionally meals: a list of
 * {type, label, time, share_pct, carbs_g, protein_g, fat_g} (phase 2).
 */
final class NutritionPlanCalculator
{
    private const MACROS = ['carbs' => 'carbs_pct', 'protein' => 'protein_pct', 'fat' => 'fat_pct'];

    /** @param  array<string, mixed>  $rules  config('nutrition_criteria') */
    public function __construct(private readonly array $rules) {}

    public function calculate(array $profile, array $plan): NutritionPlanResult
    {
        $sex = Sex::from($profile['sex']);
        $equation = EnergyEquation::tryFrom((string) ($plan['equation'] ?? '')) ?? EnergyEquation::Mifflin;
        $bodyFat = isset($plan['body_fat_pct']) && $plan['body_fat_pct'] !== '' ? (float) $plan['body_fat_pct'] : null;
        $factor = (float) ($this->rules['activity_factors'][$plan['activity'] ?? ''] ?? 1.2);
        $adjustment = (int) ($plan['adjustment'] ?? 0);
        $errors = [];
        $warnings = [];

        $bmr = $equation->bmr($sex, (int) $profile['age'], (float) $profile['height_cm'], (float) $profile['weight_kg'], $bodyFat);
        if ($bmr === null) {
            $errors[] = 'معادلة Katch-McArdle تحتاج نسبة الدهون في الجسم.';
        }

        $tdee = $bmr === null ? null : $bmr * $factor;
        $target = $tdee === null ? null : (int) (round(($tdee + $adjustment) / 10) * 10);

        $minimum = (int) $this->rules['min_kcal'][$sex->value];
        if ($target !== null && $target < $minimum) {
            $warnings[] = 'السعرات أقل من الحد الأدنى الآمن الذي نستعمله تلقائياً ('.number_format($minimum).' سعرة). تأكد أنها مقصودة وتحت متابعتك.';
        }
        if ($adjustment < 0 && $this->forbidsDeficit($profile['conditions'] ?? [])) {
            $warnings[] = 'حالة المراجع (الحمل أو سكري الحمل) لا ننقص فيها السعرات تلقائياً.';
        }

        $macros = [];
        foreach (self::MACROS as $macro => $field) {
            $pct = (int) ($plan[$field] ?? 0);
            $grams = $target === null ? 0 : (int) round($target * $pct / 100 / $this->rules['kcal_per_g'][$macro]);
            $macros[$macro] = ['pct' => $pct, 'g' => $grams, 'kcal' => $grams * $this->rules['kcal_per_g'][$macro]];
        }
        $total = array_sum(array_column($macros, 'pct'));
        if ($total !== 100) {
            $errors[] = "مجموع النسب {$total}%، ويجب أن يكون 100%.";
        }

        [$meals, $remaining] = $this->meals($plan['meals'] ?? [], $target, $macros, $errors, $warnings);

        $fluids = $this->fluids($profile, $plan['fluids_ml'] ?? null);
        if ($fluids['manual'] && $fluids['ml'] === null) {
            $errors[] = 'حالة المراجع تحتاج تحديد السوائل يدوياً حسب توصية الطبيب.';
        }

        return new NutritionPlanResult(
            bmr: $bmr === null ? null : (int) round($bmr),
            tdee: $tdee === null ? null : (int) round($tdee),
            activityFactor: $factor,
            adjustment: $adjustment,
            target: $target,
            macros: $macros,
            proteinPerKg: $target === null ? null : round($macros['protein']['g'] / (float) $profile['weight_kg'], 1),
            fluids: $fluids,
            warnings: $warnings,
            errors: $errors,
            meals: $meals,
            remaining: $remaining,
        );
    }

    /**
     * Shares each macro's grams between meals by their calorie shares, whole grams that add up
     * to the total (largest remainder), as the starting point the nutritionist edits (2a).
     *
     * @param  array<string, int>  $grams  carbs, protein, fat for the day
     * @param  list<int|float>  $shares  each meal's % of the calories
     * @return list<array{carbs_g: int, protein_g: int, fat_g: int}>
     */
    public static function distribute(array $grams, array $shares): array
    {
        $total = array_sum($shares);
        $split = array_fill(0, count($shares), []);

        foreach ($grams as $macro => $amount) {
            $exact = array_map(fn ($share) => $total > 0 ? $amount * $share / $total : 0, $shares);
            $whole = array_map(fn ($x) => (int) floor($x), $exact);
            $left = $total > 0 ? $amount - array_sum($whole) : 0;
            $order = array_keys($exact);
            usort($order, fn ($a, $b) => ($exact[$b] - $whole[$b]) <=> ($exact[$a] - $whole[$a]));
            foreach (array_slice($order, 0, max(0, $left)) as $i) {
                $whole[$i]++;
            }
            foreach ($whole as $i => $g) {
                $split[$i][$macro.'_g'] = $g;
            }
        }

        return $split;
    }

    /**
     * The meals with their calories (from the share, and from their macros at 4/9/4), and what
     * is left to share. Shares that do not make 100 stop the plan; grams left are a warning (1a).
     *
     * @return array{0: list<array<string, mixed>>, 1: array{carbs: int, protein: int, fat: int, share: int|float}|null}
     */
    private function meals(array $input, ?int $target, array $macros, array &$errors, array &$warnings): array
    {
        $types = $this->rules['plan']['meal_types'];
        $meals = [];
        foreach (array_values($input) as $meal) {
            $type = array_key_exists($meal['type'] ?? '', $types) ? $meal['type'] : 'other';
            $grams = ['carbs' => (int) ($meal['carbs_g'] ?? 0), 'protein' => (int) ($meal['protein_g'] ?? 0), 'fat' => (int) ($meal['fat_g'] ?? 0)];
            $share = (float) ($meal['share_pct'] ?? 0);
            $kcal = $target === null ? null : (int) round($target * $share / 100);
            $fromMacros = $grams['carbs'] * $this->rules['kcal_per_g']['carbs'] + $grams['protein'] * $this->rules['kcal_per_g']['protein'] + $grams['fat'] * $this->rules['kcal_per_g']['fat'];

            $meals[] = [
                'type' => $type,
                'name' => $type === 'other' && filled($meal['label'] ?? null) ? trim($meal['label']) : $types[$type],
                'time' => $meal['time'] ?? null,
                'share_pct' => self::number($share),
                'kcal' => $kcal,
                'carbs_g' => $grams['carbs'],
                'protein_g' => $grams['protein'],
                'fat_g' => $grams['fat'],
                'macro_kcal' => $fromMacros,
                'diff' => $kcal === null ? null : $fromMacros - $kcal,
            ];
        }

        if ($meals === []) {
            return [[], null];
        }

        if (count($meals) > $this->rules['plan']['max_meals']) {
            $errors[] = 'الحد الأقصى '.$this->rules['plan']['max_meals'].' وجبات في اليوم.';
        }
        $shares = array_sum(array_column($meals, 'share_pct'));
        if (abs($shares - 100) > 0.01) {
            $errors[] = 'مجموع نسب الوجبات '.self::number($shares).'%، ويجب أن يكون 100%.';
        }

        $remaining = ['share' => self::number(100 - $shares)];
        $left = [];
        foreach (['carbs' => 'الكربوهيدرات', 'protein' => 'البروتين', 'fat' => 'الدهون'] as $macro => $label) {
            $remaining[$macro] = $macros[$macro]['g'] - array_sum(array_column($meals, $macro.'_g'));
            if ($remaining[$macro] !== 0) {
                $left[] = $label.' '.($remaining[$macro] > 0 ? 'باقي '.$remaining[$macro] : 'زائد '.abs($remaining[$macro])).' غ';
            }
        }
        if ($left) {
            $warnings[] = 'لم تتطابق غرامات الوجبات مع المجموع: '.implode('، ', $left).'.';
        }

        usort($meals, fn ($a, $b) => strcmp((string) $a['time'], (string) $b['time']));

        return [$meals, $remaining];
    }

    /** 35 for 35.0, 12.5 stays: a share as people write it. */
    private static function number(float $value): int|float
    {
        $value = round($value, 1);

        return $value == floor($value) ? (int) $value : $value;
    }

    /** @return array{ml: ?int, suggested: ?int, manual: bool} */
    private function fluids(array $profile, mixed $typed): array
    {
        $rules = $this->rules['plan'];
        $manual = (bool) array_intersect($profile['conditions'] ?? [], $rules['fluids_manual_for']);
        $suggested = $manual ? null
            : (int) (round($profile['weight_kg'] * $rules['fluids_ml_per_kg'] / $rules['fluids_step']) * $rules['fluids_step']);
        $typed = $typed === null || $typed === '' ? null : (int) $typed;

        return ['ml' => $typed ?? $suggested, 'suggested' => $suggested, 'manual' => $manual];
    }

    /** Pregnancy and gestational diabetes bring templates that never take a deficit. */
    private function forbidsDeficit(array $conditions): bool
    {
        foreach ($conditions as $condition) {
            $template = $this->rules['conditions'][$condition]['template'] ?? null;
            if ($template && ($this->rules['templates'][$template]['no_deficit'] ?? false)) {
                return true;
            }
        }

        return false;
    }
}
