<?php

namespace App\Http\Controllers\Site\Auth;

use App\Enums\AccountType;
use App\Exceptions\CodeDeliveryFailed;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Services\Auth\RegistrationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;

class RegisterController extends Controller
{
    /** "?type=brand-owner" (from the restaurants page) preselects that account; anything else is ignored. */
    public function create(Request $request): View
    {
        return view('site.pages.auth.register', [
            'accountTypes' => AccountType::cases(),
            'selectedType' => AccountType::tryFrom((string) $request->query('type')) ?? AccountType::Member,
        ]);
    }

    public function store(RegisterRequest $request, RegistrationService $registration): RedirectResponse
    {
        try {
            $registration->register($request->toData());
            $notice = ['status' => 'أرسلنا رمزاً من 6 أرقام إلى بريدك.'];
        } catch (CodeDeliveryFailed $e) {
            // The account exists; the user can ask for the code again from the next page.
            $notice = ['error' => $e->userMessage()];
        }

        $request->session()->put(EmailVerificationController::SESSION_EMAIL, $request->validated('email'));

        return redirect()->route('site.verify')->with($notice);
    }
}
