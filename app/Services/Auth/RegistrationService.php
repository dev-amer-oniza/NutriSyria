<?php

namespace App\Services\Auth;

use App\Data\Auth\RegistrationData;
use App\Enums\AuthCodeCheck;
use App\Enums\AuthCodePurpose;
use App\Exceptions\CodeDeliveryFailed;
use App\Models\User;

/** Sign-up on the public site: create the account, then confirm the email with a code. */
class RegistrationService
{
    public function __construct(
        private readonly AuthCodeService $codes,
    ) {}

    /**
     * The account starts unverified, as a plain site account.
     *
     * @throws CodeDeliveryFailed the account exists; the user can ask again
     */
    public function register(RegistrationData $data): User
    {
        $user = new User([
            'name' => $data->name,
            'email' => $data->email,
            'password' => $data->password,
            'is_active' => true,
        ]);
        $user->save();

        $this->codes->send($user, AuthCodePurpose::VerifyEmail);

        return $user;
    }

    public function verifyEmail(User $user, string $code): AuthCodeCheck
    {
        $result = $this->codes->check($user, AuthCodePurpose::VerifyEmail, $code);

        if ($result === AuthCodeCheck::Valid) {
            $user->markEmailAsVerified();
        }

        return $result;
    }

    /** What a new account sees once it is ready, whichever way it signed up. */
    public static function welcomeMessage(): string
    {
        return 'أصبح حسابك جاهزاً. عرّفنا عليك في دقيقة لنحسب احتياجك.';
    }
}
