<?php

namespace App\Http\Controllers\Site\Auth;

use App\Enums\AuthCodeCheck;
use App\Enums\AuthCodePurpose;
use App\Exceptions\CodeDeliveryFailed;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\VerifyEmailRequest;
use App\Models\User;
use App\Services\Auth\AuthCodeService;
use App\Services\Auth\RegistrationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** The page between sign-up (or sign-in on an unconfirmed email) and the account. */
class EmailVerificationController extends Controller
{
    /** The email waiting for its code; set by sign-up and sign-in, never taken from the form. */
    public const SESSION_EMAIL = 'auth.verify_email';

    public function show(Request $request, AuthCodeService $codes): View|RedirectResponse
    {
        $user = $this->pendingUser($request);

        if (! $user) {
            return redirect()->route('site.login');
        }

        return view('site.pages.auth.verify-email', [
            'email' => $user->email,
            'resendIn' => $codes->secondsUntilResend($user, AuthCodePurpose::VerifyEmail),
        ]);
    }

    public function store(VerifyEmailRequest $request, RegistrationService $registration): RedirectResponse
    {
        $user = $this->pendingUser($request);

        if (! $user) {
            return redirect()->route('site.login');
        }

        $result = $registration->verifyEmail($user, $request->validated('code'));

        if ($result !== AuthCodeCheck::Valid) {
            return back()->withErrors(['code' => $result->message()]);
        }

        $request->session()->forget(self::SESSION_EMAIL);
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('home')->with('status', RegistrationService::welcomeMessage($user));
    }

    public function resend(Request $request, AuthCodeService $codes): RedirectResponse
    {
        $user = $this->pendingUser($request);

        if (! $user) {
            return redirect()->route('site.login');
        }

        try {
            $sent = $codes->send($user, AuthCodePurpose::VerifyEmail);
        } catch (CodeDeliveryFailed $e) {
            return back()->with('error', $e->userMessage());
        }

        return $sent
            ? back()->with('status', 'أرسلنا رمزاً جديداً إلى بريدك.')
            : back()->with('error', 'انتظر قليلاً قبل طلب رمز جديد.');
    }

    /** Still unconfirmed and active; null sends the visitor to sign in. */
    private function pendingUser(Request $request): ?User
    {
        $email = $request->session()->get(self::SESSION_EMAIL);

        return $email
            ? User::where('email', $email)->whereNull('email_verified_at')->where('is_active', true)->first()
            : null;
    }
}
