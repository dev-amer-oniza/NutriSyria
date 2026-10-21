<?php

namespace App\Http\Requests\Auth;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Sign-in form. Besides validating, it guards against password guessing: 5 wrong tries for
 * one email from one address lock that pair for a minute.
 */
class LoginRequest extends AuthFormRequest
{
    public const MAX_ATTEMPTS = 5;

    public const DECAY_SECONDS = 60;

    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'max:254', 'email'],
            'password' => ['required', 'string', 'max:72'],
            'remember' => ['nullable', 'boolean'],
            ...$this->honeypotRules(),
        ];
    }

    protected function passedValidation(): void
    {
        $this->ensureIsNotRateLimited();
    }

    /** One wrong try: count it and answer without saying which part was wrong. */
    public function failedAttempt(): never
    {
        RateLimiter::hit($this->throttleKey(), self::DECAY_SECONDS);

        throw ValidationException::withMessages(['email' => 'البريد الإلكتروني أو كلمة المرور غير صحيحة.']);
    }

    public function succeeded(): void
    {
        RateLimiter::clear($this->throttleKey());
    }

    private function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), self::MAX_ATTEMPTS)) {
            return;
        }

        event(new Lockout($this));

        throw ValidationException::withMessages([
            'email' => 'محاولات كثيرة. حاول مرة أخرى بعد '.RateLimiter::availableIn($this->throttleKey()).' ثانية.',
        ]);
    }

    private function throttleKey(): string
    {
        return Str::transliterate($this->string('email')->lower().'|'.$this->ip());
    }
}
