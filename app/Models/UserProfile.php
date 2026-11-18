<?php

namespace App\Models;

use App\Data\Nutrition\BmiAssessment;
use App\Enums\DailyLife;
use App\Enums\Goal;
use App\Enums\OnboardingStep;
use App\Enums\Sex;
use App\Services\Nutrition\Criteria\BmiCalculator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A site user's nutrition profile (App\Services\Nutrition\UserProfileService).
 *
 * @property Sex|null $sex
 * @property DailyLife|null $daily_life
 * @property int|null $work_days
 * @property list<array{type: string, times: int, minutes: int}>|null $exercises
 * @property Goal|null $goal
 */
class UserProfile extends Model
{
    protected $fillable = [
        'sex', 'birth_year', 'height_cm', 'weight_kg', 'daily_life', 'work_days', 'exercises', 'goal',
        'allergies', 'health_consent_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'sex' => Sex::class,
            'birth_year' => 'integer',
            'height_cm' => 'float',
            'weight_kg' => 'float',
            'daily_life' => DailyLife::class,
            'work_days' => 'integer',
            'exercises' => 'array',
            'goal' => Goal::class,
            // Health data: encrypted at rest.
            'allergies' => 'encrypted:array',
            'health_consent_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function age(): ?int
    {
        return $this->birth_year ? (int) now()->year - $this->birth_year : null;
    }

    public function hasBody(): bool
    {
        return $this->sex && $this->birth_year && $this->height_cm && $this->weight_kg;
    }

    public function hasActivity(): bool
    {
        return $this->daily_life && $this->goal;
    }

    /** "شغل أو دراسة وأنت قاعد، 3 أيام بالأسبوع · حديد عادي 3 × 60 دقيقة", or "بلا رياضة". */
    public function activitySummary(): string
    {
        if (! $this->daily_life) {
            return '—';
        }
        $day = $this->daily_life->label().($this->daily_life->hasWorkDays() && $this->work_days ? '، '.$this->work_days.' أيام بالأسبوع' : '');
        $sports = array_map(
            fn (array $e) => (config("nutrition_criteria.exercises.{$e['type']}.label") ?? $e['type']).' '.$e['times'].' × '.$e['minutes'].' دقيقة',
            $this->exercises ?? [],
        );

        return $day.' · '.($sports ? implode('، ', $sports) : 'بلا رياضة');
    }

    /**
     * What the energy calculators read: the day, its work days and the exercise.
     *
     * @return array{daily_life: string, work_days: ?int, exercises: list<array{type: string, times: int, minutes: int}>}
     */
    public function activity(): array
    {
        return [
            'daily_life' => $this->daily_life->value,
            'work_days' => $this->daily_life->hasWorkDays() ? $this->work_days : null,
            'exercises' => $this->exercises ?? [],
        ];
    }

    /** The first step still to fill in; null once body and activity are known. */
    public function nextStep(): ?OnboardingStep
    {
        return match (true) {
            ! $this->hasBody() => OnboardingStep::Body,
            ! $this->hasActivity() => OnboardingStep::Activity,
            $this->completed_at === null => OnboardingStep::Health,
            default => null,
        };
    }

    public function isAdult(): bool
    {
        return ($this->age() ?? 0) >= (int) config('nutrition_criteria.min_age');
    }

    /** Body mass index, its class and the healthy weight range; null without height and weight. */
    public function bmi(): ?BmiAssessment
    {
        return $this->height_cm && $this->weight_kg
            ? (new BmiCalculator(config('nutrition_criteria.bmi')))->assess((float) $this->height_cm, (float) $this->weight_kg, $this->goal)
            : null;
    }

    /** Enough to work out personal criteria. */
    public function canCalculate(): bool
    {
        return $this->hasBody() && $this->hasActivity() && $this->isAdult();
    }
}
