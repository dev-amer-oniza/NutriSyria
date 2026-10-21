<?php

namespace App\Http\Controllers\Site\Auth;

use App\Data\Auth\SocialProfile;
use App\Enums\AccountType;
use App\Enums\GoogleSignIn;
use App\Enums\OnboardingStep;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\GoogleRedirectRequest;
use App\Http\Requests\Auth\GoogleWhatsappRequest;
use App\Services\Auth\GoogleAuthService;
use App\Services\Auth\RegistrationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;
use Throwable;

class GoogleAuthController extends Controller
{
    /** The account type chosen before leaving for Google. */
    private const SESSION_TYPE = 'auth.google_type';

    /** A new brand owner or nutritionist back from Google, waiting to give a WhatsApp number. */
    private const SESSION_PENDING = 'auth.google_pending';

    public function redirect(GoogleRedirectRequest $request, GoogleAuthService $google): SymfonyRedirect
    {
        if (! GoogleAuthService::isConfigured()) {
            return redirect()->route('site.login')->with('error', 'الدخول بحساب Google غير متاح الآن. استعمل بريدك الإلكتروني.');
        }

        $request->session()->put(self::SESSION_TYPE, $request->accountType()->value);

        return $google->redirect();
    }

    public function callback(Request $request, GoogleAuthService $google): RedirectResponse
    {
        $type = AccountType::tryFrom((string) $request->session()->pull(self::SESSION_TYPE)) ?? AccountType::Member;

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

        return match ($google->signIn($profile, $type)) {
            GoogleSignIn::SignedIn => $this->signedIn($request, 'تم تسجيل الدخول. أهلاً بعودتك، '.$request->user()->name.'.'),
            GoogleSignIn::Created => $this->newAccount($request),
            GoogleSignIn::NeedsWhatsapp => $this->askForWhatsapp($request, $profile, $type),
            GoogleSignIn::Refused => redirect()->route('site.login')
                ->with('error', 'تعذّر الدخول بهذا الحساب من Google. جرّب حساباً آخر، أو ادخل ببريدك وكلمة المرور.'),
        };
    }

    public function whatsapp(Request $request): View|RedirectResponse
    {
        $pending = $request->session()->get(self::SESSION_PENDING);

        return $pending
            ? view('site.pages.auth.google-whatsapp', [
                'name' => $pending['profile']['name'],
                'type' => AccountType::from($pending['type']),
            ])
            : redirect()->route('site.login');
    }

    public function storeWhatsapp(GoogleWhatsappRequest $request, GoogleAuthService $google): RedirectResponse
    {
        $pending = $request->session()->get(self::SESSION_PENDING);

        if (! $pending) {
            return redirect()->route('site.login');
        }

        $result = $google->completeWithWhatsapp(
            SocialProfile::fromArray($pending['profile']),
            AccountType::from($pending['type']),
            $request->validated('whatsapp'),
        );

        $request->session()->forget(self::SESSION_PENDING);

        return $result === GoogleSignIn::Refused
            ? redirect()->route('site.login')->with('error', 'تعذّر إكمال إنشاء الحساب. حاول مرة أخرى.')
            : $this->newAccount($request);
    }

    private function askForWhatsapp(Request $request, SocialProfile $profile, AccountType $type): RedirectResponse
    {
        $request->session()->put(self::SESSION_PENDING, ['profile' => $profile->toArray(), 'type' => $type->value]);

        return redirect()->route('site.google.whatsapp');
    }

    /** A new account goes straight to the set-up. */
    private function newAccount(Request $request): RedirectResponse
    {
        $request->session()->regenerate();

        return redirect()->route('site.onboarding.show', OnboardingStep::Body)
            ->with('status', RegistrationService::welcomeMessage($request->user()));
    }

    private function signedIn(Request $request, string $message): RedirectResponse
    {
        $request->session()->regenerate();

        return redirect()->intended(route('home'))->with('status', $message);
    }
}
