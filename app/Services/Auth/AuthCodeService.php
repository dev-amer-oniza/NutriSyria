<?php

namespace App\Services\Auth;

use App\Enums\AuthCodeCheck;
use App\Enums\AuthCodePurpose;
use App\Exceptions\CodeDeliveryFailed;
use App\Models\AuthCode;
use App\Models\User;
use App\Notifications\AuthCodeNotification;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

/**
 * The 6-digit codes sent by email. A code is stored hashed, lives 10 minutes, allows 5 wrong
 * tries, and a new one can be asked for once a minute. Sending a new code replaces the old.
 */
class AuthCodeService
{
    public const TTL_MINUTES = 10;

    public const MAX_ATTEMPTS = 5;

    public const RESEND_SECONDS = 60;

    /**
     * Send a fresh code. Returns false, sending nothing, while the last one is under a minute old.
     *
     * @throws CodeDeliveryFailed
     */
    public function send(User $user, AuthCodePurpose $purpose): bool
    {
        if ($this->secondsUntilResend($user, $purpose) > 0) {
            return false;
        }

        $code = str_pad((string) random_int(0, 999_999), 6, '0', STR_PAD_LEFT);

        AuthCode::updateOrCreate(
            ['user_id' => $user->getKey(), 'purpose' => $purpose],
            [
                'code_hash' => Hash::make($code),
                'attempts' => 0,
                'expires_at' => now()->addMinutes(self::TTL_MINUTES),
                'sent_at' => now(),
            ],
        );

        try {
            $user->notify(new AuthCodeNotification($purpose, $code, self::TTL_MINUTES));
        } catch (TransportExceptionInterface $e) {
            report($e);
            // Let the user ask again right away rather than wait for a code that never came.
            AuthCode::where('user_id', $user->getKey())->where('purpose', $purpose)->delete();

            throw new CodeDeliveryFailed($e->getMessage(), previous: $e);
        }

        return true;
    }

    public function secondsUntilResend(User $user, AuthCodePurpose $purpose): int
    {
        $sentAt = $this->current($user, $purpose)?->sent_at;

        return $sentAt ? max(0, self::RESEND_SECONDS - (int) $sentAt->diffInSeconds(now())) : 0;
    }

    /** Check a code; a valid one is used up. */
    public function check(User $user, AuthCodePurpose $purpose, string $code): AuthCodeCheck
    {
        $row = $this->current($user, $purpose);

        if ($row === null || $row->expires_at->isPast()) {
            return AuthCodeCheck::Expired;
        }
        if ($row->attempts >= self::MAX_ATTEMPTS) {
            return AuthCodeCheck::TooManyAttempts;
        }
        if (! Hash::check($code, $row->code_hash)) {
            $row->increment('attempts');

            return $row->attempts >= self::MAX_ATTEMPTS ? AuthCodeCheck::TooManyAttempts : AuthCodeCheck::Invalid;
        }

        $row->delete();

        return AuthCodeCheck::Valid;
    }

    private function current(User $user, AuthCodePurpose $purpose): ?AuthCode
    {
        return AuthCode::where('user_id', $user->getKey())->where('purpose', $purpose)->first();
    }
}
