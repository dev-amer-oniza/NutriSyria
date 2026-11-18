<?php

namespace App\Http\Controllers\Site\Account;

use App\Enums\OnboardingStep;
use App\Http\Controllers\Controller;
use App\Services\Consultations\ConsultationService;
use App\Services\Nutrition\Criteria\PersonalCriteria;
use App\Services\Nutrition\Plan\NutritionPlans;
use App\Services\Nutrition\UserProfileService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** "My criteria": see each one and where it comes from (read only; the profile changes them). */
class CriteriaController extends Controller
{
    public function index(Request $request, PersonalCriteria $criteria, ConsultationService $consultations, NutritionPlans $plans): View|RedirectResponse
    {
        $profile = $request->user()->profile;

        if (! $profile?->hasBody() || ! $profile->hasActivity()) {
            return redirect()->route('site.onboarding.show', $profile?->nextStep() ?? OnboardingStep::Body)
                ->with('status', 'أكمل ملفك أولاً لنحسب معاييرك.');
        }

        $consultation = $consultations->current($request->user());

        return view('site.pages.account.criteria', [
            'profile' => $profile,
            'sheet' => $criteria->for($request->user()),
            'bmi' => $profile->bmi(),
            // With a nutritionist (or a request out), the "choose a nutritionist" advice is not shown.
            'consultation' => $consultation,
            // The nutritionist's plan, results only (3a), while the link lasts (4a).
            'plan' => $consultation?->isAccepted() ? $plans->current($consultation) : null,
            'planBy' => $consultation?->isAccepted() ? ($consultation->nutritionist->nutritionistProfile?->display_name ?? $consultation->nutritionist->name) : null,
            // Linked to a nutritionist: everything here is read only (EnsureProfileUnlocked).
            'managed' => $consultations->isManaged($request->user()),
        ]);
    }

    public function destroyHealth(Request $request, UserProfileService $profiles): RedirectResponse
    {
        $profiles->deleteHealth($request->user());

        return back()->with('status', 'حُذفت بياناتك الصحية.');
    }
}
