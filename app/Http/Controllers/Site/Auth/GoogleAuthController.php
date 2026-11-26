<?php

namespace App\Http\Controllers\Site\Auth;

use App\Enums\GoogleSignIn;
use App\Enums\OnboardingStep;
use App\Http\Controllers\Controller;
use App\Services\Auth\GoogleAuthService;
use App\Services\Auth\RegistrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;
use Throwable;

class GoogleAuthController extends Controller
{
    public function redirect(GoogleAuthService $google): SymfonyRedirect
    {
        if (! GoogleAuthService::isConfigured()) {
            return redirect()->route('site.login')->with('error', 'الدخول بحساب Google غير متاح الآن. استعمل بريدك الإلكتروني.');
        }

        return $google->redirect();
    }

    public function callback(Request $request, GoogleAuthService $google): RedirectResponse
    {
        // The person pressed "cancel" on Google's page.
        if ($request->filled('error')) {
            return redirect()->route('site.login')->with('error', 'لم يكتمل الدخول بحساب Google.');
        }

        try {
            $profile = $google->profile();
        } catch (Throwable $e) {
            // A forged or replayed answer, or Google could not be reached.
            report($e);

            return redirect()->route('site.login')->with('error', 'تعذّر الدخول بحساب Google. حاول مرة أخرى.');
        }

        return match ($google->signIn($profile)) {
            GoogleSignIn::SignedIn => $this->signedIn($request, 'تم تسجيل الدخول. أهلاً بعودتك، '.$request->user()->name.'.'),
            GoogleSignIn::Created => $this->newAccount($request),
            GoogleSignIn::Refused => redirect()->route('site.login')
                ->with('error', 'تعذّر الدخول بهذا الحساب من Google. جرّب حساباً آخر، أو ادخل ببريدك وكلمة المرور.'),
        };
    }

    /** A new account goes straight to the set-up. */
    private function newAccount(Request $request): RedirectResponse
    {
        $request->session()->regenerate();

        return redirect()->route('site.onboarding.show', OnboardingStep::Body)
            ->with('status', RegistrationService::welcomeMessage());
    }

    private function signedIn(Request $request, string $message): RedirectResponse
    {
        $request->session()->regenerate();

        return redirect()->intended(route('home'))->with('status', $message);
    }
}
