<?php

namespace App\Services\Auth;

use App\Data\Auth\RegistrationData;
use App\Enums\AuthCodeCheck;
use App\Enums\AuthCodePurpose;
use App\Models\User;

/** Sign-up on the public site: create the account, then confirm the email with a code. */
class RegistrationService
{
    public function __construct(private readonly AuthCodeService $codes) {}

    /**
     * The account starts unverified. A brand owner or nutritionist gets no role yet: the
     * request waits for the super-admin (RoleRequestService).
     *
     * @throws \App\Exceptions\CodeDeliveryFailed the account exists; the user can ask again
     */
    public function register(RegistrationData $data): User
    {
        $role = $data->accountType->requestedRole();

        $user = new User([
            'name' => $data->name,
            'email' => $data->email,
            'password' => $data->password,
            'phone' => $data->whatsapp,
            'is_active' => true,
        ]);
        // Not fillable: only set here and by the admin's decision.
        $user->forceFill([
            'requested_role' => $role,
            'role_requested_at' => $role ? now() : null,
        ])->save();

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
    public static function welcomeMessage(User $user): string
    {
        return $user->requested_role
            ? 'أصبح حسابك جاهزاً. طلبك قيد المراجعة، وسنتواصل معك عبر واتساب قبل تفعيل لوحة التحكم.'
            : 'أصبح حسابك جاهزاً. عرّفنا عليك في دقيقة لنحسب احتياجك.';
    }
}
