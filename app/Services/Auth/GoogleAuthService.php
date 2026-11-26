<?php

namespace App\Services\Auth;

use App\Data\Auth\SocialProfile;
use App\Enums\GoogleSignIn;
use App\Http\Requests\Auth\AuthFormRequest;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as ProviderUser;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * "Continue with Google". Google has already confirmed the person owns the email, so no code
 * is sent: a known email is signed in (and linked), a new one becomes a confirmed account.
 * Owning the email already opens the account through "forgot password", so this adds no new
 * way in, for panel accounts too.
 */
class GoogleAuthService
{
    private const DRIVER = 'google';

    public static function isConfigured(): bool
    {
        return filled(config('services.google.client_id')) && filled(config('services.google.client_secret'));
    }

    /** Off to Google's account chooser; Socialite adds the anti-forgery "state". */
    public function redirect(): RedirectResponse
    {
        return Socialite::driver(self::DRIVER)
            ->scopes(['openid', 'email', 'profile'])
            ->with(['prompt' => 'select_account'])
            ->redirect();
    }

    /**
     * Read the person Google sent back. Throws when the answer cannot be trusted (state
     * mismatch, a code already used); the caller turns that into a message.
     */
    public function profile(): SocialProfile
    {
        $user = Socialite::driver(self::DRIVER)->user();

        return new SocialProfile(
            providerId: (string) $user->getId(),
            email: AuthFormRequest::normalizeEmail($user->getEmail()),
            name: $this->displayName($user),
            emailVerified: $this->emailVerified($user),
        );
    }

    /** Sign in, link, or create a plain site account (the site signs up plain users only). */
    public function signIn(SocialProfile $profile): GoogleSignIn
    {
        if (! $profile->emailVerified || $profile->email === '') {
            return GoogleSignIn::Refused;
        }

        $user = User::where('google_id', $profile->providerId)->first()
            ?? User::where('email', $profile->email)->first();

        if ($user) {
            return $this->signInExisting($user, $profile);
        }

        Auth::login($this->create($profile));

        return GoogleSignIn::Created;
    }

    private function signInExisting(User $user, SocialProfile $profile): GoogleSignIn
    {
        if ($user->is_active === false || ($user->google_id && $user->google_id !== $profile->providerId)) {
            return GoogleSignIn::Refused;
        }

        $changes = ['google_id' => $profile->providerId];

        // Never confirmed: whoever set its password may not be the email's owner (the
        // owner's choice: link and drop that password). A password can be set again
        // through "forgot password".
        if (! $user->hasVerifiedEmail()) {
            $changes += ['email_verified_at' => now(), 'password' => null, 'remember_token' => Str::random(60)];
        }

        $user->forceFill($changes)->save();
        Auth::login($user);

        return GoogleSignIn::SignedIn;
    }

    private function create(SocialProfile $profile): User
    {
        $user = new User([
            'name' => $profile->name,
            'email' => $profile->email,
            'is_active' => true,
        ]);
        $user->forceFill([
            'google_id' => $profile->providerId,
            'email_verified_at' => now(),
        ])->save();

        return $user;
    }

    private function displayName(ProviderUser $user): string
    {
        $name = trim((string) ($user->getName() ?: $user->getNickname()));

        return mb_substr($name !== '' ? $name : Str::before((string) $user->getEmail(), '@'), 0, 60);
    }

    /** Google sends email_verified (OpenID) or verified_email (older API). */
    private function emailVerified(ProviderUser $user): bool
    {
        $raw = method_exists($user, 'getRaw') ? $user->getRaw() : [];

        return filter_var($raw['email_verified'] ?? $raw['verified_email'] ?? false, FILTER_VALIDATE_BOOLEAN);
    }
}
