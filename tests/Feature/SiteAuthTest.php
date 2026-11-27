<?php

namespace Tests\Feature;

use App\Auth\Access;
use App\Enums\AuthCodePurpose;
use App\Filament\Clusters\AccessControl\Resources\Users\Pages\ListUsers;
use App\Http\Controllers\Site\Auth\EmailVerificationController;
use App\Http\Controllers\Site\Auth\PasswordResetController;
use App\Models\AuthCode;
use App\Models\User;
use App\Notifications\AuthCodeNotification;
use App\Services\Auth\AuthCodeService;
use App\Services\Auth\RoleRequestService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Tests\TestCase;

class SiteAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Notification::fake();
    }

    private function register(array $overrides = []): TestResponse
    {
        return $this->post('/register', $overrides + [
            'name' => 'سارة الأحمد',
            'email' => 'Sara@Example.com ',
            'password' => 'zaatar-2026',
        ]);
    }

    /** The last code mailed to the user for a purpose, read from the faked notification. */
    private function lastCode(User $user, AuthCodePurpose $purpose = AuthCodePurpose::VerifyEmail): string
    {
        $code = null;
        Notification::assertSentTo($user, AuthCodeNotification::class, function (AuthCodeNotification $n) use (&$code, $purpose) {
            if ($n->purpose === $purpose) {
                $code = $n->code;
            }

            return true;
        });

        return $code;
    }

    private function verifiedUser(array $attributes = []): User
    {
        return User::forceCreate($attributes + [
            'name' => 'رامي', 'email' => 'rami@example.com', 'password' => 'olive-oil-9',
            'is_active' => true, 'email_verified_at' => now(),
        ]);
    }

    public function test_pages_open_for_guests(): void
    {
        foreach (['/register', '/login', '/forgot-password'] as $page) {
            $this->get($page)->assertOk();
        }
        $this->get('/verify-email')->assertRedirect('/login');
        $this->get('/reset-password')->assertRedirect('/forgot-password');
    }

    public function test_a_user_signs_up_and_confirms_the_email_with_a_code(): void
    {
        $this->register()->assertRedirect('/verify-email');

        $user = User::where('email', 'sara@example.com')->sole();
        $this->assertNull($user->email_verified_at);
        $this->assertNull($user->requested_role);
        $this->assertCount(0, $user->roles);
        $this->assertGuest();
        $this->assertSame('sara@example.com', session(EmailVerificationController::SESSION_EMAIL));

        $code = $this->lastCode($user);
        $this->assertNotSame($code, AuthCode::sole()->code_hash, 'the code is stored hashed');

        $this->post('/verify-email', ['code' => '١٢٣'])->assertSessionHasErrors('code');
        $this->post('/verify-email', ['code' => $code])->assertRedirect('/onboarding/body');

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->assertSame(0, AuthCode::count(), 'a used code is gone');
        $this->assertFalse($user->fresh()->canAccessPanel(Filament::getPanel('admin')));
    }

    public function test_a_code_in_arabic_digits_is_accepted(): void
    {
        $this->register();
        $user = User::sole();
        $arabic = strtr($this->lastCode($user), ['0' => '٠', '1' => '١', '2' => '٢', '3' => '٣', '4' => '٤', '5' => '٥', '6' => '٦', '7' => '٧', '8' => '٨', '9' => '٩']);

        $this->post('/verify-email', ['code' => $arabic])->assertRedirect('/onboarding/body');
        $this->assertAuthenticatedAs($user);
    }

    public function test_the_site_signs_up_plain_users_only(): void
    {
        // An old link or a crafted form asking for a role or a kind is ignored.
        $this->register(['account_type' => 'brand-owner', 'whatsapp' => '0912 345 678', 'brand_kind' => 'brand'])->assertRedirect('/verify-email');

        $user = User::sole();
        $this->assertNull($user->requested_role);
        $this->assertNull($user->phone);
        $this->assertCount(0, $user->roles);
        $this->assertFalse($user->canAccessPanel(Filament::getPanel('admin')));

        $this->get('/register?type=brand-owner')->assertOk()
            ->assertDontSee('name="account_type"', false)->assertDontSee('name="whatsapp"', false)->assertDontSee('name="brand_kind"', false);
    }

    public function test_a_member_cannot_slip_in_a_number_or_a_role(): void
    {
        $this->register(['whatsapp' => '0912345678', 'requested_role' => Access::SUPER_ADMIN]);

        $user = User::sole();
        $this->assertNull($user->phone);
        $this->assertNull($user->requested_role);
    }

    public function test_declining_a_request_leaves_a_plain_account(): void
    {
        $this->register(['account_type' => 'nutritionist', 'whatsapp' => '+963944000111']);
        $user = User::sole();

        app(RoleRequestService::class)->decline($user);

        $this->assertNull($user->fresh()->requested_role);
        $this->assertCount(0, $user->fresh()->roles);
    }

    public function test_sign_up_input_is_checked(): void
    {
        $this->verifiedUser(['email' => 'taken@example.com']);

        $cases = [
            'password' => ['password' => '12345678'],
            'password ' => ['password' => 'short'],
            'password  ' => ['password' => 'sara@example.com', 'email' => 'sara@example.com'],
            'email' => ['email' => 'taken@example.com'],
            'email ' => ['email' => 'not-an-email'],
            'name' => ['name' => '<script>'],
            'website' => ['website' => 'http://spam.example'],
        ];

        foreach ($cases as $field => $input) {
            $this->register($input)->assertSessionHasErrors(trim($field));
        }

        $this->assertSame(1, User::count());
        Notification::assertNothingSent();
    }

    public function test_a_code_stops_after_five_wrong_tries_and_expires(): void
    {
        $this->register();
        $user = User::sole();
        $code = $this->lastCode($user);
        $wrong = $code === '000000' ? '111111' : '000000';

        for ($i = 0; $i < AuthCodeService::MAX_ATTEMPTS; $i++) {
            $this->post('/verify-email', ['code' => $wrong])->assertSessionHasErrors('code');
        }
        $this->post('/verify-email', ['code' => $code])->assertSessionHasErrors('code');
        $this->assertGuest();

        AuthCode::query()->update(['attempts' => 0, 'expires_at' => now()->subMinute()]);
        $this->post('/verify-email', ['code' => $code])->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_a_new_code_can_be_asked_for_once_a_minute(): void
    {
        $this->register();
        $user = User::sole();

        $this->post('/verify-email/resend')->assertSessionHas('error');
        Notification::assertSentToTimes($user, AuthCodeNotification::class, 1);

        $this->travel(61)->seconds();
        $this->post('/verify-email/resend')->assertSessionHas('status');
        Notification::assertSentToTimes($user, AuthCodeNotification::class, 2);
    }

    public function test_sign_in_answers_the_same_for_any_wrong_detail(): void
    {
        $this->verifiedUser();
        $this->verifiedUser(['email' => 'stopped@example.com', 'is_active' => false]);

        foreach ([
            ['email' => 'rami@example.com', 'password' => 'wrong-pass'],
            ['email' => 'nobody@example.com', 'password' => 'olive-oil-9'],
            ['email' => 'stopped@example.com', 'password' => 'olive-oil-9'],
        ] as $input) {
            $this->post('/login', $input)->assertSessionHasErrors(['email' => 'البريد الإلكتروني أو كلمة المرور غير صحيحة.']);
        }
        $this->assertGuest();
    }

    public function test_sign_in_works_and_locks_after_five_wrong_passwords(): void
    {
        $user = $this->verifiedUser();

        $this->post('/login', ['email' => 'RAMI@example.com', 'password' => 'olive-oil-9'])->assertRedirect('/');
        $this->assertAuthenticatedAs($user);
        $this->post('/logout')->assertRedirect('/');
        $this->assertGuest();

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => 'rami@example.com', 'password' => 'wrong-pass']);
        }
        $this->post('/login', ['email' => 'rami@example.com', 'password' => 'olive-oil-9'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_signing_in_before_confirming_sends_a_code(): void
    {
        $user = $this->verifiedUser(['email_verified_at' => null]);

        $this->post('/login', ['email' => 'rami@example.com', 'password' => 'olive-oil-9'])->assertRedirect('/verify-email');

        $this->assertGuest();
        $this->post('/verify-email', ['code' => $this->lastCode($user)])->assertRedirect('/onboarding/body');
        $this->assertAuthenticatedAs($user);
    }

    public function test_a_forgotten_password_is_reset_with_a_code(): void
    {
        $user = $this->verifiedUser();

        $this->post('/forgot-password', ['email' => 'nobody@example.com'])->assertRedirect('/reset-password');
        Notification::assertNothingSent();

        $this->post('/forgot-password', ['email' => 'rami@example.com'])->assertRedirect('/reset-password');
        $this->assertSame('rami@example.com', session(PasswordResetController::SESSION_EMAIL));
        $code = $this->lastCode($user, AuthCodePurpose::ResetPassword);

        $this->post('/reset-password', ['code' => $code, 'password' => '12345678'])->assertSessionHasErrors('password');
        $this->post('/reset-password', ['code' => $code, 'password' => 'new-sumac-77'])->assertRedirect('/login');

        $this->post('/login', ['email' => 'rami@example.com', 'password' => 'olive-oil-9'])->assertSessionHasErrors('email');
        $this->post('/login', ['email' => 'rami@example.com', 'password' => 'new-sumac-77'])->assertRedirect('/');
        $this->assertAuthenticatedAs($user);
    }

    public function test_signed_in_users_skip_the_guest_pages(): void
    {
        $this->actingAs($this->verifiedUser());

        $this->get('/login')->assertRedirect('/');
        $this->get('/')->assertSee('خروج');
    }

    public function test_the_admin_still_answers_a_request_left_from_before(): void
    {
        $this->register();
        $applicant = User::where('email', 'sara@example.com')->sole();
        $applicant->forceFill(['requested_role' => Access::NUTRITIONIST, 'role_requested_at' => now()])->save();

        $this->signInAs(Access::SUPER_ADMIN);

        Livewire::test(ListUsers::class)
            ->assertCanSeeTableRecords([$applicant])
            ->callTableAction('approveRole', $applicant);

        $this->assertTrue($applicant->fresh()->hasRole(Access::NUTRITIONIST));
        $this->assertNull($applicant->fresh()->requested_role);
    }

    public function test_only_the_admin_can_answer_role_requests(): void
    {
        $this->register(['account_type' => 'nutritionist', 'whatsapp' => '+963944000111']);
        $applicant = User::where('email', 'sara@example.com')->sole();

        $this->signInAs(Access::NUTRITIONIST);

        $this->get('/admin/access-control/users')->assertForbidden();
        $this->assertNull($applicant->fresh()->roles->first());
    }

    public function test_messages_slide_in_at_the_side_and_close_by_themselves(): void
    {
        $this->verifiedUser();

        $this->post('/login', ['email' => 'rami@example.com', 'password' => 'olive-oil-9'])
            ->assertSessionHas('status', 'تم تسجيل الدخول. أهلاً بعودتك، رامي.');

        $this->get('/')
            ->assertSee('class="ns-toasts"', false)
            ->assertSee('x-data="flash(2500)"', false)
            ->assertSee('aria-label="إغلاق الرسالة"', false);

        $this->withSession(['error' => 'تعذّر الإرسال.'])->get('/')
            ->assertSee('x-data="flash(5000)"', false);

        // Account pages show it the same way, at the top.
        auth()->logout();
        $this->withSession(['status' => 'أرسلنا رمزاً جديداً إلى بريدك.'])->get(route('site.password.request'))
            ->assertSee('class="ns-toasts"', false);
    }

    public function test_a_nutritionist_has_no_my_nutritionist_in_the_account_menu(): void
    {
        $this->signInAs(Access::NUTRITIONIST);
        $this->get('/')->assertOk()->assertSee(route('site.daily'), false)->assertDontSee(route('site.my-nutritionist'), false);
    }

    public function test_a_plain_user_finds_my_nutritionist_in_the_account_menu(): void
    {
        $this->verifiedUser();
        $this->actingAs(User::where('email', 'rami@example.com')->sole());
        $this->get('/')->assertOk()->assertSee(route('site.my-nutritionist'), false);
    }
}
