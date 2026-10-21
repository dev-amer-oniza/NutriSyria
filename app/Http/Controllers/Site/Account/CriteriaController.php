<?php

namespace App\Http\Controllers\Site\Account;

use App\Enums\OnboardingStep;
use App\Exceptions\CriterionLocked;
use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\CriterionRequest;
use App\Services\Nutrition\Criteria\PersonalCriteria;
use App\Services\Nutrition\UserProfileService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** "My criteria": see each one and where it comes from, change it, or put it back. */
class CriteriaController extends Controller
{
    public function index(Request $request, PersonalCriteria $criteria): View|RedirectResponse
    {
        $profile = $request->user()->profile;

        if (! $profile?->hasBody() || ! $profile->hasActivity()) {
            return redirect()->route('site.onboarding.show', $profile?->nextStep() ?? OnboardingStep::Body)
                ->with('status', 'أكمل ملفك أولاً لنحسب معاييرك.');
        }

        return view('site.pages.account.criteria', [
            'profile' => $profile,
            'sheet' => $criteria->for($request->user()),
        ]);
    }

    public function update(CriterionRequest $request, string $criterion, PersonalCriteria $criteria): RedirectResponse
    {
        try {
            $criteria->setByUser($request->user(), $criterion, (float) $request->validated('value'));
        } catch (CriterionLocked $e) {
            return back()->with('error', $e->userMessage());
        }

        return back()->with('status', 'حُفظ المعيار. ستُقيَّم الوجبات به من الآن.');
    }

    public function reset(Request $request, string $criterion, PersonalCriteria $criteria): RedirectResponse
    {
        try {
            $criteria->resetByUser($request->user(), $criterion);
        } catch (CriterionLocked $e) {
            return back()->with('error', $e->userMessage());
        }

        return back()->with('status', 'عاد المعيار إلى القيمة المحسوبة تلقائياً.');
    }

    public function destroyHealth(Request $request, UserProfileService $profiles): RedirectResponse
    {
        $profiles->deleteHealth($request->user());

        return back()->with('status', 'حُذفت بياناتك الصحية.');
    }
}
