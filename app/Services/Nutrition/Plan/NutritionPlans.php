<?php

namespace App\Services\Nutrition\Plan;

use App\Data\Nutrition\NutritionPlanResult;
use App\Models\ConsultationRequest;
use App\Models\UserProfile;

/**
 * Reads a client's profile for NutritionPlanCalculator (the thin, database side). Saving and
 * removing a plan go through ClientWorkspace, which checks the link first.
 */
class NutritionPlans
{
    /** The plan's criteria: what saving it sets as the nutritionist's values. */
    public const CRITERIA = ['energy_kcal' => null, 'carbohydrate_g' => 'carbs', 'protein_g' => 'protein', 'fat_g' => 'fat'];

    public function calculate(UserProfile $profile, array $inputs): NutritionPlanResult
    {
        return (new NutritionPlanCalculator(config('nutrition_criteria')))->calculate([
            'sex' => $profile->sex->value,
            'age' => $profile->age(),
            'height_cm' => $profile->height_cm,
            'weight_kg' => $profile->weight_kg,
            'conditions' => $profile->health_conditions ?? [],
        ], $inputs);
    }

    /** The saved plan worked out on the client's current profile; null without a plan. */
    public function current(ConsultationRequest $link): ?NutritionPlanResult
    {
        $plan = $link->plan;
        $profile = $link->user->profile;

        return $plan && $profile?->canCalculate() ? $this->calculate($profile, $plan->inputs($profile->activity_level->value)) : null;
    }

    /** What a new plan starts from: Mifflin, the profile's goal change and the current template's shares. */
    public function defaults(ConsultationRequest $link, array $templateMacros): array
    {
        $profile = $link->user->profile;

        return [
            'equation' => 'mifflin',
            'body_fat_pct' => null,
            'activity' => $profile->activity_level->value,
            'adjustment' => (int) config('nutrition_criteria.goal_adjustment.'.$profile->goal->value),
            'carbs_pct' => $templateMacros['carbs'],
            'protein_pct' => $templateMacros['protein'],
            'fat_pct' => $templateMacros['fat'],
            'fluids_ml' => null,
        ];
    }

    /**
     * The plan meal restaurant dishes are judged against: the first lunch by time, with its
     * calories (its share of the day's) and grams as the nutritionist set them. Null without a
     * lunch: then the usual share of the day applies (2a).
     *
     * @param  list<array<string, mixed>>  $meals  as stored on the plan
     * @return array{name: string, time: ?string, share: float, kcal: int, targets: array<string, float>}|null
     */
    public static function referenceMeal(array $meals, float $dayKcal): ?array
    {
        $lunches = array_values(array_filter($meals, fn (array $m) => ($m['type'] ?? null) === 'lunch'));
        usort($lunches, fn ($a, $b) => strcmp((string) ($a['time'] ?? ''), (string) ($b['time'] ?? '')));
        $lunch = $lunches[0] ?? null;
        if (! $lunch || (float) ($lunch['share_pct'] ?? 0) <= 0) {
            return null;
        }

        $share = (float) $lunch['share_pct'] / 100;
        $kcal = (int) round($dayKcal * $share);

        return [
            'name' => config('nutrition_criteria.plan.meal_types.lunch'),
            'time' => isset($lunch['time']) ? substr((string) $lunch['time'], 0, 5) : null,
            'share' => $share,
            'kcal' => $kcal,
            'targets' => [
                'energy_kcal' => (float) $kcal,
                'carbohydrate_g' => (float) ($lunch['carbs_g'] ?? 0),
                'protein_g' => (float) ($lunch['protein_g'] ?? 0),
                'fat_g' => (float) ($lunch['fat_g'] ?? 0),
            ],
        ];
    }

    /** @return array<string, float> the criterion values a valid result sets */
    public static function criteriaOf(NutritionPlanResult $result): array
    {
        $values = [];
        foreach (self::CRITERIA as $criterion => $macro) {
            $values[$criterion] = (float) ($macro === null ? $result->target : $result->macros[$macro]['g']);
        }

        return $values;
    }
}
