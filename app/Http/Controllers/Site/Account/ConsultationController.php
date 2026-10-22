<?php

namespace App\Http\Controllers\Site\Account;

use App\Enums\OnboardingStep;
use App\Exceptions\ConsultationRefused;
use App\Http\Controllers\Controller;
use App\Http\Requests\Consultations\ConsultationRequestForm;
use App\Models\ConsultationRequest;
use App\Services\Consultations\ConsultationService;
use App\Services\Consultations\NutritionistDirectory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Asking a nutritionist for a consultation, and "my nutritionist" (the request or the link). */
class ConsultationController extends Controller
{
    public function create(Request $request, int $profile, NutritionistDirectory $directory, ConsultationService $consultations): View|RedirectResponse
    {
        $nutritionist = $directory->find($profile) ?? abort(404);

        try {
            $consultations->ensureCanRequest($request->user(), $nutritionist);
        } catch (ConsultationRefused $e) {
            return $this->refused($request, $e, $profile);
        }

        return view('site.pages.consultations.create', [
            'profile' => $nutritionist,
            'whatsapp' => $request->user()->phone,
        ]);
    }

    public function store(ConsultationRequestForm $request, int $profile, NutritionistDirectory $directory, ConsultationService $consultations): RedirectResponse
    {
        $nutritionist = $directory->find($profile) ?? abort(404);

        try {
            $consultations->request($request->user(), $nutritionist, $request->validated('reason'), $request->validated('whatsapp'));
        } catch (ConsultationRefused $e) {
            return $this->refused($request, $e, $profile);
        }

        return redirect()->route('site.my-nutritionist')
            ->with('status', 'أُرسل طلبك. يصلك إيميل حين يرد الأخصائي.');
    }

    public function show(Request $request, ConsultationService $consultations): View
    {
        return view('site.pages.consultations.mine', [
            'current' => $consultations->current($request->user()),
            'history' => $consultations->history($request->user()),
        ]);
    }

    public function cancel(Request $request, int $consultation, ConsultationService $consultations): RedirectResponse
    {
        return $this->step(
            fn () => $consultations->cancel($request->user(), $this->own($request, $consultation)),
            'أُلغي الطلب. يمكنك اختيار أخصائي آخر.',
        );
    }

    public function end(Request $request, int $consultation, ConsultationService $consultations): RedirectResponse
    {
        return $this->step(
            fn () => $consultations->end($request->user(), $this->own($request, $consultation)),
            'انتهى الارتباط. عادت معاييرك إلى قيمك أنت، ولم يعد الأخصائي يرى سجلك.',
        );
    }

    /** Someone else's request is "not found", never "forbidden". */
    private function own(Request $request, int $id): ConsultationRequest
    {
        return $request->user()->consultationRequests()->findOrFail($id);
    }

    private function step(callable $step, string $done): RedirectResponse
    {
        try {
            $step();
        } catch (ConsultationRefused $e) {
            return back()->with('error', $e->userMessage());
        }

        return redirect()->route('site.my-nutritionist')->with('status', $done);
    }

    private function refused(Request $request, ConsultationRefused $e, int $profile): RedirectResponse
    {
        $redirect = match ($e->next) {
            ConsultationRefused::COMPLETE_PROFILE => redirect()->route('site.onboarding.show',
                $request->user()->profile?->nextStep() ?? OnboardingStep::Body),
            ConsultationRefused::HAS_OPEN => redirect()->route('site.my-nutritionist'),
            default => redirect()->route('site.nutritionists.show', $profile),
        };

        return $redirect->with('error', $e->userMessage());
    }
}
