<?php

namespace App\Services\Nutrition\Criteria;

use App\Data\Nutrition\CriteriaSheet;
use App\Data\Nutrition\CriterionValue;
use App\Enums\CriterionSource;
use App\Exceptions\CriterionLocked;
use App\Models\User;
use App\Models\UserCriterion;
use App\Models\UserCriterionChange;
use App\Models\UserProfile;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * A person's final criteria. Each criterion is resolved on its own: the nutritionist's
 * value, else the person's own, else the default computed from the profile. A value set by
 * the nutritionist is locked for the person (the owner's decision). Every change is logged.
 */
class PersonalCriteria
{
    /** Later sources win (CriterionSource order). */
    private const PRECEDENCE = [CriterionSource::Nutritionist, CriterionSource::User];

    /** The sheet for a person; null while the profile is incomplete or under the minimum age. */
    public function for(User $user): ?CriteriaSheet
    {
        $profile = $user->profile;

        if (! $profile?->canCalculate()) {
            return null;
        }

        $sheet = (new CriteriaCalculator(config('nutrition_criteria')))->calculate($this->asArray($profile));
        $overrides = UserCriterion::where('user_id', $user->getKey())->get()->groupBy('criterion');

        foreach ($overrides as $key => $rows) {
            $winner = $this->winning($rows);

            if (! $winner) {
                continue;
            }

            $value = $sheet->get($key)?->overriddenBy($winner->source, $winner->value, $winner->note)
                ?? $this->activated($key, $winner);

            if ($value) {
                $sheet = $sheet->with($value);
            }
        }

        return $sheet;
    }

    public function setByUser(User $user, string $criterion, float $value): void
    {
        if ($this->lockedFor($user, $criterion)) {
            throw new CriterionLocked;
        }

        $this->store($user, $criterion, CriterionSource::User, $value, null, $user);
    }

    public function resetByUser(User $user, string $criterion): void
    {
        if ($this->lockedFor($user, $criterion)) {
            throw new CriterionLocked;
        }

        $this->forget($user, $criterion, CriterionSource::User, $user);
    }

    /** For the nutritionist's page (next phase); kept here so the rules live in one place. */
    public function setByNutritionist(User $user, string $criterion, float $value, User $nutritionist, ?string $note = null): void
    {
        $this->store($user, $criterion, CriterionSource::Nutritionist, $value, $note, $nutritionist);
    }

    public function clearNutritionist(User $user, ?User $by = null): void
    {
        UserCriterion::where('user_id', $user->getKey())->where('source', CriterionSource::Nutritionist)
            ->pluck('criterion')
            ->each(fn (string $criterion) => $this->forget($user, $criterion, CriterionSource::Nutritionist, $by));
    }

    public function lockedFor(User $user, string $criterion): bool
    {
        return UserCriterion::where('user_id', $user->getKey())
            ->where('criterion', $criterion)
            ->where('source', CriterionSource::Nutritionist)
            ->exists();
    }

    /** @return array{0: float, 1: float} the range a value may take */
    public static function range(string $criterion): array
    {
        $definition = config("nutrition_criteria.criteria.$criterion")
            ?? throw new InvalidArgumentException("Unknown criterion [$criterion].");

        return $definition['range'];
    }

    private function store(User $user, string $criterion, CriterionSource $source, float $value, ?string $note, ?User $by): void
    {
        self::range($criterion);

        $row = UserCriterion::firstOrNew(['user_id' => $user->getKey(), 'criterion' => $criterion, 'source' => $source]);
        $old = $row->exists ? $row->value : null;

        $row->fill(['value' => $value, 'note' => $note, 'set_by' => $by?->getKey()])->save();
        $this->log($user, $criterion, $source, $old, $value, $by);
    }

    private function forget(User $user, string $criterion, CriterionSource $source, ?User $by): void
    {
        $row = UserCriterion::where('user_id', $user->getKey())->where('criterion', $criterion)->where('source', $source)->first();

        if ($row) {
            $row->delete();
            $this->log($user, $criterion, $source, $row->value, null, $by);
        }
    }

    private function log(User $user, string $criterion, CriterionSource $source, ?float $old, ?float $new, ?User $by): void
    {
        UserCriterionChange::create([
            'user_id' => $user->getKey(), 'criterion' => $criterion, 'source' => $source,
            'old_value' => $old, 'new_value' => $new, 'changed_by' => $by?->getKey(),
        ]);
    }

    /** @param  Collection<int, UserCriterion>  $rows */
    private function winning(Collection $rows): ?UserCriterion
    {
        foreach (self::PRECEDENCE as $source) {
            if ($row = $rows->first(fn (UserCriterion $r) => $r->source === $source)) {
                return $row;
            }
        }

        return null;
    }

    /** A criterion with no default for this person (a meal carbohydrate limit without diabetes), switched on by a person or nutritionist. */
    private function activated(string $key, UserCriterion $row): ?CriterionValue
    {
        $definition = config("nutrition_criteria.criteria.$key");

        return $definition ? new CriterionValue(
            $key, $definition['label'], $definition['unit'], $definition['bound'], $definition['scope'],
            $definition['nutrient'], $row->value, $row->source, $row->source->label(), $row->note ?? $definition['note'] ?? null,
        ) : null;
    }

    private function asArray(UserProfile $profile): array
    {
        return [
            'sex' => $profile->sex->value,
            'age' => $profile->age(),
            'height_cm' => $profile->height_cm,
            'weight_kg' => $profile->weight_kg,
            'activity' => $profile->activity_level->value,
            'goal' => $profile->goal->value,
            'conditions' => $profile->health_conditions ?? [],
            'allergens' => $profile->allergies ?? [],
        ];
    }
}
