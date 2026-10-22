<?php

namespace App\View\Composers;

use App\Enums\OnboardingStep;
use App\Services\Consultations\ConsultationService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Gives the site layout the set-up step a signed-in person still has to do, if any. */
class ProfileReminderComposer
{
    public function __construct(
        private readonly Request $request,
        private readonly ConsultationService $consultations,
    ) {}

    public function compose(View $view): void
    {
        $user = $this->request->user();

        // Not on the set-up itself, and not while a nutritionist manages the profile.
        $nextStep = $user && ! $this->request->routeIs('site.onboarding.*') && ! $this->consultations->isManaged($user)
            ? ($user->profile ? $user->profile->nextStep() : OnboardingStep::Body)
            : null;

        $view->with('profileNextStep', $nextStep);
    }
}
