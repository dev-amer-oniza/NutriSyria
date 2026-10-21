<?php

namespace App\Http\Controllers\Site\Auth;

use App\Enums\AuthCodeCheck;
use App\Exceptions\CodeDeliveryFailed;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Services\Auth\PasswordResetService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PasswordResetController extends Controller
{
    /** The email a reset code was asked for. */
    public const SESSION_EMAIL = 'auth.reset_email';

    public function create(): View
    {
        return view('site.pages.auth.forgot-password');
    }

    public function sendCode(ForgotPasswordRequest $request, PasswordResetService $reset): RedirectResponse
    {
        try {
            $reset->sendCode($request->validated('email'));
        } catch (CodeDeliveryFailed $e) {
            return back()->onlyInput('email')->with('error', $e->userMessage());
        }

        $request->session()->put(self::SESSION_EMAIL, $request->validated('email'));

        // The same answer whether or not the email has an account.
        return redirect()->route('site.password.reset')
            ->with('status', 'إن كان هذا البريد مسجّلاً لدينا، فقد أرسلنا إليه رمزاً من 6 أرقام.');
    }

    public function edit(Request $request): View|RedirectResponse
    {
        $email = $request->session()->get(self::SESSION_EMAIL);

        return $email
            ? view('site.pages.auth.reset-password', ['email' => $email])
            : redirect()->route('site.password.request');
    }

    public function update(ResetPasswordRequest $request, PasswordResetService $reset): RedirectResponse
    {
        $result = $reset->reset(
            $request->validated('email'),
            $request->validated('code'),
            $request->validated('password'),
        );

        if ($result !== AuthCodeCheck::Valid) {
            return back()->withErrors(['code' => $result->message()]);
        }

        $request->session()->forget(self::SESSION_EMAIL);

        return redirect()->route('site.login')->with('status', 'تم تغيير كلمة المرور. سجّل الدخول بها الآن.');
    }
}
