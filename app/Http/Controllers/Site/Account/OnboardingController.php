<?php

namespace App\Http\Controllers\Site\Account;

use App\Enums\ActivityLevel;
use App\Enums\Allergen;
use App\Enums\Goal;
use App\Enums\HealthCondition;
use App\Enums\OnboardingStep;
use App\Enums\Sex;
use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\ActivityStepRequest;
use App\Http\Requests\Profile\BodyStepRequest;
use App\Http\Requests\Profile\HealthStepRequest;
use App\Services\Consultations\ConsultationService;
use App\Services\Nutrition\Criteria\PersonalCriteria;
use App\Services\Nutrition\UserProfileService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** The three-step account set-up, then the daily needs it gives. */
class OnboardingController extends Controller
{
    public function show(Request $request, OnboardingStep $step, UserProfileService $profiles): View|RedirectResponse
    {
        $profile = $profiles->profileOf($request->user());

        // A later step needs the earlier ones.
        if ($profile->nextStep() && $step->number() > $profile->nextStep()->number()) {
            return redirect()->route('site.onboarding.show', $profile->nextStep());
        }

        return view("site.pages.onboarding.{$step->value}", [
            'step' => $step,
            'profile' => $profile,
            'options' => match ($step) {
                OnboardingStep::Body => ['sexes' => Sex::cases()],
                OnboardingStep::Activity => ['levels' => ActivityLevel::cases(), 'goals' => Goal::cases()],
                OnboardingStep::Health => [
                    'groups' => HealthCondition::grouped($profile->sex),
                    'allergens' => Allergen::cases(),
                ],
            },
        ]);
    }

    public function storeBody(BodyStepRequest $request, UserProfileService $profiles): RedirectResponse
    {
        $profiles->saveBody($request->user(), $request->validated());

        return redirect()->route('site.onboarding.show', OnboardingStep::Activity);
    }

    public function storeActivity(ActivityStepRequest $request, UserProfileService $profiles): RedirectResponse
    {
        $profiles->saveActivity($request->user(), $request->validated());

        return redirect()->route('site.onboarding.show', OnboardingStep::Health);
    }

    public function storeHealth(HealthStepRequest $request, UserProfileService $profiles): RedirectResponse
    {
        $profiles->saveHealth($request->user(), $request->conditions(), $request->allergens());

        return redirect()->route('site.onboarding.done');
    }

    public function done(Request $request, PersonalCriteria $criteria, ConsultationService $consultations): View|RedirectResponse
    {
        $profile = $request->user()->profile;

        if (! $profile?->hasBody() || ! $profile->hasActivity()) {
            return redirect()->route('site.onboarding.show', $profile?->nextStep() ?? OnboardingStep::Body);
        }

        return view('site.pages.onboarding.done', [
            'profile' => $profile,
            'sheet' => $criteria->for($request->user()),
            'bmi' => $profile->bmi(),
            'goalUrl' => $consultations->isManaged($request->user()) ? null : route('site.onboarding.show', OnboardingStep::Activity),
        ]);
    }
}
