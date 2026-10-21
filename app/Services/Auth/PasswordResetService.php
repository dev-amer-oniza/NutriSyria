<?php

namespace App\Services\Auth;

use App\Enums\AuthCodeCheck;
use App\Enums\AuthCodePurpose;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** "Forgot password": a code by email, then a new password. */
class PasswordResetService
{
    public function __construct(private readonly AuthCodeService $codes) {}

    /**
     * Sends a code when the email belongs to an active account, and does nothing otherwise:
     * the page answers the same either way, so it cannot be used to find accounts.
     *
     * @throws \App\Exceptions\CodeDeliveryFailed
     */
    public function sendCode(string $email): void
    {
        $user = $this->activeUser($email);

        if ($user) {
            $this->codes->send($user, AuthCodePurpose::ResetPassword);
        }
    }

    /**
     * Setting a new password also confirms the email (the code proved it) and signs the
     * account out everywhere else.
     */
    public function reset(string $email, string $code, #[\SensitiveParameter] string $password): AuthCodeCheck
    {
        $user = $this->activeUser($email);

        if (! $user) {
            return AuthCodeCheck::Expired;
        }

        $result = $this->codes->check($user, AuthCodePurpose::ResetPassword, $code);

        if ($result === AuthCodeCheck::Valid) {
            $user->forceFill([
                'password' => $password,
                'remember_token' => Str::random(60),
                'email_verified_at' => $user->email_verified_at ?? now(),
            ])->save();

            $this->endOtherSessions($user);
        }

        return $result;
    }

    private function activeUser(string $email): ?User
    {
        return User::where('email', $email)->where('is_active', true)->first();
    }

    private function endOtherSessions(User $user): void
    {
        if (config('session.driver') === 'database') {
            DB::connection(config('session.connection'))
                ->table(config('session.table', 'sessions'))
                ->where('user_id', $user->getKey())
                ->delete();
        }
    }
}
