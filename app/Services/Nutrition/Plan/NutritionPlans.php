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
