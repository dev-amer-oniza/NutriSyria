<?php

namespace App\Http\Controllers\Site\Auth;

use App\Enums\LoginOutcome;
use App\Exceptions\CodeDeliveryFailed;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\Auth\LoginService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LoginController extends Controller
{
    public function create(): View
    {
        return view('site.pages.auth.login');
    }

    public function store(LoginRequest $request, LoginService $login): RedirectResponse
    {
        try {
            $outcome = $login->attempt(
                $request->validated('email'),
                $request->validated('password'),
                $request->boolean('remember'),
            );
        } catch (CodeDeliveryFailed $e) {
            return back()->onlyInput('email')->with('error', $e->userMessage());
        }

        return match ($outcome) {
            LoginOutcome::InvalidCredentials => $request->failedAttempt(),
            LoginOutcome::EmailUnverified => $this->toVerification($request),
            LoginOutcome::Authenticated => $this->signedIn($request),
        };
    }

    public function destroy(Request $request, LoginService $login): RedirectResponse
    {
        $login->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }

    private function toVerification(LoginRequest $request): RedirectResponse
    {
        $request->succeeded();
        $request->session()->put(EmailVerificationController::SESSION_EMAIL, $request->validated('email'));

        return redirect()->route('site.verify')
            ->with('status', 'لم تؤكد بريدك بعد. أرسلنا إليه رمزاً من 6 أرقام.');
    }

    private function signedIn(LoginRequest $request): RedirectResponse
    {
        $request->succeeded();
        $request->session()->regenerate();

        return redirect()->intended(route('home'));
    }
}
