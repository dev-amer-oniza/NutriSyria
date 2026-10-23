<?php

namespace App\Http\Controllers\Site\Account;

use App\Enums\OnboardingStep;
use App\Exceptions\CriterionLocked;
use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\CriterionRequest;
use App\Http\Requests\Profile\TemplateRequest;
use App\Services\Consultations\ConsultationService;
use App\Services\Nutrition\Criteria\PersonalCriteria;
use App\Services\Nutrition\Plan\NutritionPlans;
use App\Services\Nutrition\UserProfileService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** "My criteria": see each one and where it comes from, change it, or put it back. */
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
            'selectableTemplates' => PersonalCriteria::selectableTemplates(),
            'templateLocked' => $criteria->templateLockedFor($request->user()),
            // With a nutritionist (or a request out), the "choose a nutritionist" advice is not shown.
            'consultation' => $consultation,
            // The nutritionist's plan, results only (3a), while the link lasts (4a).
            'plan' => $consultation?->isAccepted() ? $plans->current($consultation) : null,
            'planBy' => $consultation?->isAccepted() ? ($consultation->nutritionist->nutritionistProfile?->display_name ?? $consultation->nutritionist->name) : null,
            // Linked to a nutritionist: everything here is read only (EnsureProfileUnlocked).
            'managed' => $consultations->isManaged($request->user()),
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

    public function chooseTemplate(TemplateRequest $request, PersonalCriteria $criteria): RedirectResponse
    {
        try {
            $criteria->chooseTemplateByUser($request->user(), $request->validated('template'));
        } catch (CriterionLocked) {
            return back()->with('error', 'اختار أخصائيّك قالبك. تواصل معه إن أردت تغييره.');
        }

        return back()->with('status', 'تغيّر قالبك، وأُعيد حساب الكربوهيدرات والبروتين والدهون.');
    }

    public function resetTemplate(Request $request, PersonalCriteria $criteria): RedirectResponse
    {
        try {
            $criteria->resetTemplateByUser($request->user());
        } catch (CriterionLocked) {
            return back()->with('error', 'اختار أخصائيّك قالبك. تواصل معه إن أردت تغييره.');
        }

        return back()->with('status', 'عاد قالبك إلى ما يناسب حالتك تلقائياً.');
    }

    public function destroyHealth(Request $request, UserProfileService $profiles): RedirectResponse
    {
        $profiles->deleteHealth($request->user());

        return back()->with('status', 'حُذفت بياناتك الصحية.');
    }
}
