<?php

namespace App\Services\Auth;

use App\Enums\AuthCodePurpose;
use App\Enums\LoginOutcome;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class LoginService
{
    /** Compared against when the email is unknown, so both cases take the same time. */
    private const DUMMY_HASH = '$2y$12$d3u7jtSfe4KA3cKVIMLV/uCyoVkU9vi/xoLGN8OJGQjEYROxx4wxe';

    public function __construct(private readonly AuthCodeService $codes) {}

    /**
     * An unknown email, a wrong password and a stopped account all read the same to the
     * visitor. A right password on an unconfirmed email sends a new code instead of signing in.
     *
     * @throws \App\Exceptions\CodeDeliveryFailed
     */
    public function attempt(string $email, #[\SensitiveParameter] string $password, bool $remember): LoginOutcome
    {
        $user = User::where('email', $email)->first();
        $passwordMatches = Hash::check($password, $user?->password ?? self::DUMMY_HASH);

        if (! $user || ! $passwordMatches || $user->is_active === false) {
            return LoginOutcome::InvalidCredentials;
        }

        if (! $user->hasVerifiedEmail()) {
            $this->codes->send($user, AuthCodePurpose::VerifyEmail);

            return LoginOutcome::EmailUnverified;
        }

        Auth::login($user, $remember);

        return LoginOutcome::Authenticated;
    }

    public function logout(): void
    {
        Auth::guard('web')->logout();
    }
}
