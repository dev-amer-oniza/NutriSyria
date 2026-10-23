<?php

namespace App\Services\Consultations;

use App\Data\Nutrition\NutritionPlanResult;
use App\Enums\ActivityLevel;
use App\Enums\Allergen;
use App\Enums\ConsultationStatus;
use App\Enums\EnergyEquation;
use App\Enums\HealthCondition;
use App\Enums\Sex;
use App\Models\ConsultationRequest;
use App\Models\User;
use App\Notifications\SiteNotification;
use App\Services\Nutrition\Criteria\PersonalCriteria;
use App\Services\Nutrition\Plan\NutritionPlans;
use App\Services\Nutrition\UserProfileService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * "My clients": what a nutritionist may do for a person they are linked to (the owner's
 * decisions for part 3):
 *  - see the profile and health record (read only: the person owns their data);
 *  - choose the diet template and set any criterion, each with an optional note the person
 *    sees; what they set is locked for the person (PersonalCriteria);
 *  - update the person's profile and health record, which the person cannot change
 *    while linked (the owner's later decision 3a);
 *  - build a nutrition plan (equation, calorie change, macro shares, fluids) that sets the
 *    person's calories and macros, and follows later changes to their weight or activity;
 *  - end the link, with an optional reason the person sees in their bell.
 * Every step checks the link is accepted and theirs; anything else is "not found". The person
 * gets one notice per editing session (NOTIFY_EVERY_MINUTES), not one per change.
 */
class ClientWorkspace
{
    public const NOTIFY_EVERY_MINUTES = 30;

    /** The note the person sees on the values a plan sets. */
    public const PLAN_NOTE = 'من خطة التغذية التي وضعها أخصائيّك.';

    public function __construct(
        private readonly PersonalCriteria $criteria,
        private readonly ConsultationService $consultations,
        private readonly UserProfileService $profiles,
        private readonly NutritionPlans $plans,
    ) {}

    /** The links this nutritionist has now (accepted, theirs). */
    public function clients(User $nutritionist): Builder
    {
        return ConsultationRequest::query()
            ->where('nutritionist_id', $nutritionist->getKey())
            ->where('status', ConsultationStatus::Accepted);
    }

    public function chooseTemplate(User $nutritionist, ConsultationRequest $link, string $template, ?string $note = null): void
    {
        $this->ensureLinked($nutritionist, $link);
        $this->criteria->setTemplateByNutritionist($link->user, $template, $nutritionist, self::clean($note));
        $this->changed($link);
    }

    public function resetTemplate(User $nutritionist, ConsultationRequest $link): void
    {
        $this->ensureLinked($nutritionist, $link);
        $this->criteria->resetTemplateByNutritionist($link->user, $nutritionist);
        $this->changed($link);
    }

    public function setCriterion(User $nutritionist, ConsultationRequest $link, string $criterion, float $value, ?string $note = null): void
    {
        $this->ensureLinked($nutritionist, $link);

        [$min, $max] = PersonalCriteria::range($criterion);
        if ($value < $min || $value > $max) {
            throw new InvalidArgumentException("[$criterion] must be between $min and $max.");
        }

        $this->criteria->setByNutritionist($link->user, $criterion, $value, $nutritionist, self::clean($note));
        $this->changed($link);
    }

    public function resetCriterion(User $nutritionist, ConsultationRequest $link, string $criterion): void
    {
        $this->ensureLinked($nutritionist, $link);
        PersonalCriteria::range($criterion);
        $this->criteria->resetByNutritionist($link->user, $criterion, $nutritionist);
        $this->changed($link);
    }

    /**
     * The person's body, activity, goal and health record, as the nutritionist updates them
     * (validated by the panel form). Women-only conditions are dropped for men.
     *
     * @param  array{sex: string, birth_year: int, height_cm: float, weight_kg: float, activity_level: string, goal: string, health_conditions?: list<string>, allergies?: list<string>}  $data
     */
    public function updateProfile(User $nutritionist, ConsultationRequest $link, array $data): void
    {
        $this->ensureLinked($nutritionist, $link);

        $client = $link->user;
        $sex = Sex::from($data['sex']);

        $this->profiles->saveBody($client, Arr::only($data, ['sex', 'birth_year', 'height_cm', 'weight_kg']));
        $this->profiles->saveActivity($client, Arr::only($data, ['activity_level', 'goal']));
        $this->profiles->saveHealth(
            $client,
            array_values(array_filter(
                array_map(fn (string $c) => HealthCondition::from($c), $data['health_conditions'] ?? []),
                fn (HealthCondition $c) => $c->appliesTo($sex),
            )),
            array_map(fn (string $a) => Allergen::from($a), $data['allergies'] ?? []),
        );

        // A new weight or activity changes the plan's numbers: keep the client's values in step.
        $plan = $link->plan()->first();
        if ($plan) {
            $profile = $client->profile->refresh();
            $result = $this->plans->calculate($profile, $plan->inputs($profile->activity_level->value));
            if ($result->isValid()) {
                $this->applyPlan($nutritionist, $link, $result);
            }
        }

        $this->changed($link);
    }

    /**
     * Saves the nutrition plan (phase 1 of the nutritionist's plan) and sets the client's
     * calories and macros from it as the nutritionist's values, so meals are judged on it.
     * The activity level is the profile's own and is updated with it.
     *
     * @param  array{equation: string, body_fat_pct: ?float, activity: string, adjustment: int, carbs_pct: int, protein_pct: int, fat_pct: int, fluids_ml: ?int, meals?: list<array<string, mixed>>}  $inputs
     *
     * @throws InvalidArgumentException when the plan has errors or a value is out of range
     */
    public function savePlan(User $nutritionist, ConsultationRequest $link, array $inputs): NutritionPlanResult
    {
        $this->ensureLinked($nutritionist, $link);
        EnergyEquation::from($inputs['equation']);
        ActivityLevel::from($inputs['activity']);

        $result = $this->plans->calculate($link->user->profile, $inputs);

        DB::transaction(function () use ($nutritionist, $link, $inputs, $result) {
            $this->applyPlan($nutritionist, $link, $result);
            $this->profiles->saveActivity($link->user, ['activity_level' => $inputs['activity']]);

            $link->plan()->firstOrNew()->forceFill([
                'updated_by' => $nutritionist->getKey(),
                'equation' => $inputs['equation'],
                'body_fat_pct' => filled($inputs['body_fat_pct'] ?? null) ? (float) $inputs['body_fat_pct'] : null,
                'adjustment_kcal' => (int) $inputs['adjustment'],
                'carbs_pct' => (int) $inputs['carbs_pct'],
                'protein_pct' => (int) $inputs['protein_pct'],
                'fat_pct' => (int) $inputs['fat_pct'],
                'fluids_ml' => filled($inputs['fluids_ml'] ?? null) ? (int) $inputs['fluids_ml'] : null,
                'meals' => self::planMeals($result),
            ])->save();
        });

        $this->changed($link);

        return $result;
    }

    /** Removes the plan; the calories and macros go back to the template's or the person's. */
    public function removePlan(User $nutritionist, ConsultationRequest $link): void
    {
        $this->ensureLinked($nutritionist, $link);

        foreach (array_keys(NutritionPlans::CRITERIA) as $criterion) {
            $this->criteria->resetByNutritionist($link->user, $criterion, $nutritionist);
        }
        $link->plan()->delete();
        $this->changed($link);
    }

    public function end(User $nutritionist, ConsultationRequest $link, ?string $reason = null): void
    {
        $this->ensureLinked($nutritionist, $link);
        $this->consultations->end($nutritionist, $link, $reason);
    }

    /** @return list<array<string, mixed>>|null the meals as stored: by time, without what is derived */
    private static function planMeals(NutritionPlanResult $result): ?array
    {
        $meals = array_map(fn (array $m) => [
            'type' => $m['type'],
            'label' => $m['type'] === 'other' ? $m['name'] : null,
            'time' => $m['time'],
            'share_pct' => $m['share_pct'],
            'carbs_g' => $m['carbs_g'],
            'protein_g' => $m['protein_g'],
            'fat_g' => $m['fat_g'],
        ], $result->meals);

        return $meals ?: null;
    }

    /** Sets the plan's calories and macros as the nutritionist's values (checked first, all or none). */
    private function applyPlan(User $nutritionist, ConsultationRequest $link, NutritionPlanResult $result): void
    {
        if (! $result->isValid()) {
            throw new InvalidArgumentException(implode(' ', $result->errors));
        }

        $values = NutritionPlans::criteriaOf($result);
        foreach ($values as $criterion => $value) {
            [$min, $max] = PersonalCriteria::range($criterion);
            if ($value < $min || $value > $max) {
                $label = config("nutrition_criteria.criteria.$criterion.label");
                throw new InvalidArgumentException("{$label} في الخطة ({$value}) خارج المجال المسموح ({$min}–{$max}).");
            }
        }

        foreach ($values as $criterion => $value) {
            $this->criteria->setByNutritionist($link->user, $criterion, $value, $nutritionist, self::PLAN_NOTE);
        }
    }

    private function ensureLinked(User $nutritionist, ConsultationRequest $link): void
    {
        abort_unless(
            $link->nutritionist_id === $nutritionist->getKey() && $link->refresh()->isAccepted(),
            404,
        );
    }

    /** One notice per editing session: none if the person was told in the last half hour. */
    private function changed(ConsultationRequest $link): void
    {
        if ($link->client_notified_at?->gt(now()->subMinutes(self::NOTIFY_EVERY_MINUTES))) {
            return;
        }

        $link->forceFill(['client_notified_at' => now()])->save();
        $link->user->notify(SiteNotification::updatedByNutritionist($link));
    }

    private static function clean(?string $note): ?string
    {
        return filled($note) ? trim($note) : null;
    }
}
