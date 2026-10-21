<?php

namespace Tests\Feature;

use App\Auth\Access;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User as GoogleUser;
use Mockery;
use Tests\TestCase;

class GoogleAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['services.google' => ['client_id' => 'test-id', 'client_secret' => 'test-secret', 'redirect' => '/auth/google/callback']]);
    }

    /** Google answers with this person on the next callback. */
    private function googleReturns(string $email = 'Layla@Gmail.com', bool $verified = true, string $id = 'g-123', string $name = 'ليلى حداد'): void
    {
        $user = (new GoogleUser)
            ->setRaw(['sub' => $id, 'email' => $email, 'email_verified' => $verified, 'name' => $name])
            ->map(['id' => $id, 'email' => $email, 'name' => $name, 'nickname' => null]);

        $provider = Mockery::mock();
        $provider->shouldReceive('user')->andReturn($user);
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
    }

    private function existing(array $attributes = []): User
    {
        return User::forceCreate($attributes + [
            'name' => 'ليلى', 'email' => 'layla@gmail.com', 'password' => 'olive-oil-9',
            'is_active' => true, 'email_verified_at' => now(),
        ]);
    }

    public function test_the_button_shows_only_when_google_is_set_up(): void
    {
        $this->get('/login')->assertSee('المتابعة مع Google');
        $this->get('/register')->assertSee('?type=', false);

        config(['services.google.client_id' => null]);
        $this->get('/login')->assertDontSee('المتابعة مع Google');
        $this->get('/auth/google/redirect')->assertRedirect('/login')->assertSessionHas('error');
    }

    public function test_the_redirect_goes_to_google_and_remembers_the_account_type(): void
    {
        $response = $this->get('/auth/google/redirect?type=brand-owner');

        $this->assertStringStartsWith('https://accounts.google.com/o/oauth2/auth', $response->headers->get('Location'));
        $this->assertStringContainsString('state=', $response->headers->get('Location'));
        $this->assertSame('brand-owner', session('auth.google_type'));

        $this->get('/auth/google/redirect?type=super-admin')->assertSessionHasErrors('type');
    }

    public function test_a_new_person_gets_a_confirmed_account(): void
    {
        $this->googleReturns();

        $this->get('/auth/google/callback?state=x&code=y')->assertRedirect('/onboarding/body');

        $user = User::sole();
        $this->assertAuthenticatedAs($user);
        $this->assertSame('layla@gmail.com', $user->email);
        $this->assertSame('ليلى حداد', $user->name);
        $this->assertSame('g-123', $user->google_id);
        $this->assertNull($user->password);
        $this->assertNotNull($user->email_verified_at);
        $this->assertNull($user->requested_role);
    }

    public function test_an_existing_account_is_linked_and_keeps_its_password(): void
    {
        $user = $this->existing();
        $this->googleReturns();

        $this->get('/auth/google/callback')->assertRedirect('/')->assertSessionHas('status');

        $this->assertAuthenticatedAs($user);
        $this->assertSame('g-123', $user->fresh()->google_id);
        $this->post('/logout');
        $this->post('/login', ['email' => 'layla@gmail.com', 'password' => 'olive-oil-9'])->assertRedirect('/');
    }

    public function test_an_unconfirmed_account_is_confirmed_and_its_password_dropped(): void
    {
        // Someone else may have signed up with Layla's email and a password of their own.
        $user = $this->existing(['email_verified_at' => null]);
        $this->googleReturns();

        $this->get('/auth/google/callback')->assertRedirect('/');

        $user->refresh();
        $this->assertNotNull($user->email_verified_at);
        $this->assertNull($user->password);
        $this->post('/logout');
        $this->post('/login', ['email' => 'layla@gmail.com', 'password' => 'olive-oil-9'])->assertSessionHasErrors('email');
    }

    public function test_google_is_refused_when_it_cannot_be_trusted(): void
    {
        $this->existing(['email' => 'stopped@gmail.com', 'is_active' => false]);
        $this->existing(['email' => 'other@gmail.com', 'google_id' => 'g-999']);

        foreach ([
            ['layla@gmail.com', false, 'g-123'],   // Google did not confirm the email
            ['stopped@gmail.com', true, 'g-200'],  // stopped account
            ['other@gmail.com', true, 'g-300'],    // linked to another Google account
        ] as [$email, $verified, $id]) {
            $this->googleReturns($email, $verified, $id);
            $this->get('/auth/google/callback')->assertRedirect('/login')->assertSessionHas('error');
            $this->assertGuest();
        }

        $this->assertNull(User::where('email', 'layla@gmail.com')->first());
    }

    public function test_a_forged_or_cancelled_answer_goes_back_to_sign_in(): void
    {
        $provider = Mockery::mock();
        $provider->shouldReceive('user')->andThrow(new InvalidStateException);
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $this->get('/auth/google/callback')->assertRedirect('/login')->assertSessionHas('error');
        $this->get('/auth/google/callback?error=access_denied')->assertRedirect('/login')->assertSessionHas('error');
        $this->assertGuest();
        $this->assertSame(0, User::count());
    }

    public function test_a_new_brand_owner_gives_a_whatsapp_number_first(): void
    {
        $this->get('/auth/google/redirect?type=brand-owner');
        $this->googleReturns();

        $this->get('/auth/google/callback')->assertRedirect('/auth/google/whatsapp');
        $this->assertGuest();
        $this->assertSame(0, User::count(), 'no account before the number');

        $this->get('/auth/google/whatsapp')->assertOk()->assertSee('ليلى حداد');
        $this->post('/auth/google/whatsapp', ['whatsapp' => 'abc'])->assertSessionHasErrors('whatsapp');
        $this->post('/auth/google/whatsapp', ['whatsapp' => '٠٩٤٤ ١٢٣ ٤٥٦'])->assertRedirect('/onboarding/body');

        $user = User::sole();
        $this->assertAuthenticatedAs($user);
        $this->assertSame('+963944123456', $user->phone);
        $this->assertSame(Access::BRAND_OWNER, $user->requested_role);
        $this->assertCount(0, $user->roles);
        $this->get('/auth/google/whatsapp')->assertRedirect('/');
    }

    public function test_the_whatsapp_page_needs_a_google_answer(): void
    {
        $this->get('/auth/google/whatsapp')->assertRedirect('/login');
        $this->post('/auth/google/whatsapp', ['whatsapp' => '0944123456'])->assertRedirect('/login');
        $this->assertSame(0, User::count());
    }
}
