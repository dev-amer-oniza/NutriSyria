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
 * carbs_pct, protein_pct, fat_pct, fluids_ml (nullable).
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
        );
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
