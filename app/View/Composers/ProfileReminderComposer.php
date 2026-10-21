<?php

namespace App\View\Composers;

use App\Enums\OnboardingStep;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Gives the site layout the set-up step a signed-in person still has to do, if any. */
class ProfileReminderComposer
{
    public function __construct(private readonly Request $request) {}

    public function compose(View $view): void
    {
        $user = $this->request->user();

        $nextStep = $user && ! $this->request->routeIs('site.onboarding.*')
            ? ($user->profile ? $user->profile->nextStep() : OnboardingStep::Body)
            : null;

        $view->with('profileNextStep', $nextStep);
    }
}
