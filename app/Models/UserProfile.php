<?php

namespace App\Models;

use App\Data\Nutrition\BmiAssessment;
use App\Services\Nutrition\Criteria\BmiCalculator;
use App\Enums\ActivityLevel;
use App\Enums\Goal;
use App\Enums\OnboardingStep;
use App\Enums\Sex;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A site user's nutrition profile (App\Services\Nutrition\UserProfileService). */
class UserProfile extends Model
{
    protected $connection = 'central';

    protected $fillable = [
        'sex', 'birth_year', 'height_cm', 'weight_kg', 'activity_level', 'goal',
        'health_conditions', 'allergies', 'health_consent_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'sex' => Sex::class,
            'birth_year' => 'integer',
            'height_cm' => 'float',
            'weight_kg' => 'float',
            'activity_level' => ActivityLevel::class,
            'goal' => Goal::class,
            // Health data: encrypted at rest.
            'health_conditions' => 'encrypted:array',
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
        return $this->activity_level && $this->goal;
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
            ? (new BmiCalculator(config('nutrition_criteria.bmi')))->assess((float) $this->height_cm, (float) $this->weight_kg, $this->goal, $this->health_conditions ?? [])
            : null;
    }

    /** Enough to work out personal criteria. */
    public function canCalculate(): bool
    {
        return $this->hasBody() && $this->hasActivity() && $this->isAdult();
    }
}
