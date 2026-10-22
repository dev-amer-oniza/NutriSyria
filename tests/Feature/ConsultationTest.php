<?php

namespace Tests\Feature;

use App\Auth\Access;
use App\Enums\ConsultationStatus;
use App\Enums\CriterionSource;
use App\Filament\Clusters\Consultations\Resources\ConsultationRequests\ConsultationRequestResource;
use App\Filament\Clusters\Consultations\Resources\ConsultationRequests\Pages\ListConsultationRequests;
use App\Models\ConsultationRequest;
use App\Models\NutritionistProfile;
use App\Models\User;
use App\Models\UserCriterion;
use App\Notifications\ConsultationNotification;
use App\Services\Consultations\ConsultationService;
use App\Services\Nutrition\Criteria\PersonalCriteria;
use Filament\Actions\Testing\TestAction;
use Filament\Notifications\DatabaseNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class ConsultationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Notification::fake();
    }

    private function nutritionist(string $email = 'reem@example.com'): NutritionistProfile
    {
        $user = User::forceCreate(['name' => $email, 'email' => $email, 'password' => 'x-secret-1', 'is_active' => true,
            'email_verified_at' => now(), 'phone' => '+96393'.random_int(1000000, 9999999)]);
        $user->assignRole(Access::NUTRITIONIST);

        return $user->nutritionistProfile()->create([
            'display_name' => 'د. '.$email, 'specialties' => ['diabetes'], 'city' => 'damascus',
            'bio' => str_repeat('أعمل مع مرضى السكري منذ سنوات. ', 3), 'consultation_mode' => 'online',
            'accepting_clients' => true,
        ]);
    }

    private function client(string $email = 'sara@example.com', bool $withProfile = true): User
    {
        $user = User::forceCreate(['name' => 'سارة', 'email' => $email, 'password' => 'x-secret-1', 'is_active' => true, 'email_verified_at' => now()]);

        if ($withProfile) {
            $user->profile()->create([
                'sex' => 'female', 'birth_year' => now()->year - 34, 'height_cm' => 165, 'weight_kg' => 78,
                'activity_level' => 'light', 'goal' => 'lose', 'health_conditions' => ['diabetes'], 'allergies' => [],
                'health_consent_at' => now(), 'completed_at' => now(),
            ]);
        }

        return $user;
    }

    private function send(User $client, NutritionistProfile $profile, array $input = []): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($client)->post(route('site.consultations.create', $profile), $input + [
            'reason' => 'عندي سكري من النوع الثاني وأريد خطة تناسب أكلنا.',
            'whatsapp' => '0912 345 678',
            'consent' => '1',
        ]);
    }

    private function pending(User $client, NutritionistProfile $profile, string $whatsapp = '+963912345678'): ConsultationRequest
    {
        return app(ConsultationService::class)->request($client, $profile, 'أريد خطة لإنقاص الوزن مع السكري.', $whatsapp);
    }

    public function test_a_person_sends_a_request_and_the_nutritionist_is_told(): void
    {
        $profile = $this->nutritionist();
        $client = $this->client();

        $this->actingAs($client)->get(route('site.nutritionists.show', $profile))
            ->assertOk()->assertSee('اطلب استشارة');
        $this->get(route('site.consultations.create', $profile))->assertOk()->assertSee('لماذا تريد الاستشارة؟');

        $this->send($client, $profile)->assertRedirect(route('site.my-nutritionist'));

        $request = ConsultationRequest::sole();
        $this->assertSame(ConsultationStatus::Pending, $request->status);
        $this->assertSame($profile->user_id, (int) $request->nutritionist_id);
        $this->assertNotNull($request->consented_at);
        $this->assertTrue($request->expires_at->between(now()->addDays(7)->subMinute(), now()->addDays(7)->addMinute()));
        $this->assertSame('+963912345678', $client->fresh()->phone, 'the number is kept for next time');

        Notification::assertSentTo($profile->user, ConsultationNotification::class,
            fn (ConsultationNotification $n) => str_contains($n->subject, 'سارة') && str_contains(implode(' ', $n->lines), '34 سنة'));
        Notification::assertSentTo($profile->user, DatabaseNotification::class); // the panel bell

        $this->get(route('site.my-nutritionist'))->assertOk()->assertSee('بانتظار الرد')->assertSee('إلغاء الطلب');
        $this->get(route('site.nutritionists.show', $profile))->assertSee('طلبك بانتظار الرد');
    }

    public function test_the_form_needs_a_reason_a_number_and_consent(): void
    {
        $profile = $this->nutritionist();
        $client = $this->client();
        User::forceCreate(['name' => 'x', 'email' => 'x@example.com', 'password' => 'x-secret-1', 'phone' => '+963955555555']);

        $this->send($client, $profile, ['reason' => 'قصير', 'whatsapp' => '', 'consent' => ''])
            ->assertSessionHasErrors(['reason', 'whatsapp', 'consent']);
        $this->send($client, $profile, ['whatsapp' => '0955 555 555'])->assertSessionHasErrors(['whatsapp']);
        $this->send($client, $profile, ['website' => 'http://spam.example'])->assertSessionHasErrors(['website']);
        $this->send($client, $profile, ['reason' => str_repeat('أ', 501)])->assertSessionHasErrors(['reason']);

        $this->assertSame(0, ConsultationRequest::count());
    }

    public function test_a_guest_signs_in_first_and_a_person_without_basics_completes_them(): void
    {
        $profile = $this->nutritionist();

        $this->get(route('site.nutritionists.show', $profile))->assertSee('اطلب استشارة');
        $this->get(route('site.consultations.create', $profile))->assertRedirect(route('site.login'));

        $this->actingAs($this->client(withProfile: false))
            ->get(route('site.consultations.create', $profile))
            ->assertRedirect(route('site.onboarding.show', 'body'))
            ->assertSessionHas('error');
    }

    public function test_one_nutritionist_at_a_time_and_not_oneself(): void
    {
        $reem = $this->nutritionist();
        $omar = $this->nutritionist('omar@example.com');
        $client = $this->client();

        $this->send($client, $reem);
        $this->send($client, $omar)->assertRedirect(route('site.my-nutritionist'))->assertSessionHas('error');
        $this->get(route('site.nutritionists.show', $omar))->assertSee('لديك أخصائي أو طلب حالياً');
        $this->assertSame(1, ConsultationRequest::count());

        // A nutritionist may book a colleague, never themself.
        $omar->user->profile()->create(['sex' => 'male', 'birth_year' => 1985, 'height_cm' => 175, 'weight_kg' => 80, 'activity_level' => 'light', 'goal' => 'maintain']);
        $this->send($omar->user, $omar, ['whatsapp' => $omar->user->phone])->assertRedirect(route('site.nutritionists.show', $omar))->assertSessionHas('error');
        $this->actingAs($omar->user)->get(route('site.nutritionists.show', $omar))->assertSee('هذه صفحتك');
    }

    public function test_a_hidden_nutritionist_cannot_be_booked(): void
    {
        $profile = $this->nutritionist();
        $profile->forceFill(['accepting_clients' => false])->save();

        $this->send($this->client(), $profile)->assertNotFound();
        $this->assertSame(0, ConsultationRequest::count());
    }

    public function test_a_person_cancels_their_own_request_only(): void
    {
        $profile = $this->nutritionist();
        $client = $this->client();
        $request = $this->pending($client, $profile);

        $this->actingAs($this->client('other@example.com'))
            ->post(route('site.consultations.cancel', $request->id))->assertNotFound();

        $this->actingAs($client)->post(route('site.consultations.cancel', $request->id))
            ->assertRedirect(route('site.my-nutritionist'));
        $this->assertSame(ConsultationStatus::Cancelled, $request->fresh()->status);

        // Free to ask someone else now.
        $this->send($client, $this->nutritionist('omar@example.com'))->assertSessionHasNoErrors();
        $this->assertSame(2, ConsultationRequest::count());
    }

    public function test_an_unanswered_request_expires_after_seven_days(): void
    {
        $profile = $this->nutritionist();
        $client = $this->client();
        $request = $this->pending($client, $profile);

        $this->travel(7)->days();
        $this->travel(1)->minutes();

        $this->actingAs($client)->get(route('site.my-nutritionist'))->assertOk()->assertSee('انتهت مدته');
        $this->assertSame(ConsultationStatus::Expired, $request->fresh()->status);
        $this->expectException(\App\Exceptions\ConsultationRefused::class);
        app(ConsultationService::class)->accept($profile->user, $request);
    }

    public function test_the_nutritionist_accepts_from_the_panel_and_sees_the_number_after(): void
    {
        $profile = $this->nutritionist();
        $client = $this->client();
        $request = $this->pending($client, $profile);
        $someoneElses = $this->pending($this->client('lina@example.com'), $this->nutritionist('omar@example.com'), '+963944444444');

        $this->actingAs($profile->user);
        \Filament\Facades\Filament::setCurrentPanel('admin');

        $this->assertSame('1', ConsultationRequestResource::getNavigationBadge());
        Livewire::test(ListConsultationRequests::class)
            ->assertCanSeeTableRecords([$request])
            ->assertCanNotSeeTableRecords([$someoneElses])
            ->assertSee('34 سنة')
            ->assertDontSee('+963912345678')
            ->callAction(TestAction::make('accept')->table($request))
            ->assertNotified('قُبل الطلب. تواصل معه على واتساب.');

        $this->assertSame(ConsultationStatus::Accepted, $request->fresh()->status);
        Notification::assertSentTo($client, ConsultationNotification::class, fn ($n) => str_contains($n->subject, 'قبل'));

        Livewire::test(ListConsultationRequests::class)->assertSee('+963912345678');
        $this->assertNull(ConsultationRequestResource::getNavigationBadge());
    }

    public function test_declining_needs_no_reason_but_passes_one_on(): void
    {
        $profile = $this->nutritionist();
        $client = $this->client();
        $request = $this->pending($client, $profile);

        $this->actingAs($profile->user);
        \Filament\Facades\Filament::setCurrentPanel('admin');

        Livewire::test(ListConsultationRequests::class)
            ->callAction(TestAction::make('decline')->table($request), ['reason' => 'جدولي ممتلئ هذا الشهر.']);

        $request->refresh();
        $this->assertSame(ConsultationStatus::Declined, $request->status);
        $this->assertSame('جدولي ممتلئ هذا الشهر.', $request->decline_reason);
        Notification::assertSentTo($client, ConsultationNotification::class,
            fn ($n) => in_array('السبب: جدولي ممتلئ هذا الشهر.', $n->lines, true));
    }

    public function test_only_the_addressed_nutritionist_answers(): void
    {
        $request = $this->pending($this->client(), $this->nutritionist());
        $omar = $this->nutritionist('omar@example.com')->user;

        $this->assertFalse($omar->can('respond', $request));
        $this->assertFalse($omar->can('view', $request));
        $this->expectException(\Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class);
        app(ConsultationService::class)->accept($omar, $request);
    }

    public function test_the_admin_does_not_read_consultation_requests(): void
    {
        $this->signInAs(Access::SUPER_ADMIN);

        $this->assertFalse(ConsultationRequestResource::canAccess());
        $this->get(ConsultationRequestResource::getUrl())->assertForbidden();
    }

    public function test_ending_the_link_gives_the_person_their_own_criteria_back(): void
    {
        $profile = $this->nutritionist();
        $client = $this->client();
        $request = $this->pending($client, $profile);
        app(ConsultationService::class)->accept($profile->user, $request);

        $criteria = app(PersonalCriteria::class);
        $criteria->setByNutritionist($client, 'sodium_mg', 1500, $profile->user, 'ضغط مرتفع');
        $this->assertTrue($criteria->lockedFor($client, 'sodium_mg'));

        $this->actingAs($client)->get(route('site.my-nutritionist'))
            ->assertSee('أخصائيّك الآن')->assertSee('تواصل على واتساب')->assertSee('إنهاء الارتباط');
        $this->get(route('site.criteria'))->assertDontSee('اختر أخصائياً');

        $this->post(route('site.consultations.end', $request->id))->assertRedirect(route('site.my-nutritionist'));

        $request->refresh();
        $this->assertSame(ConsultationStatus::Ended, $request->status);
        $this->assertSame($client->id, (int) $request->ended_by);
        $this->assertFalse($criteria->lockedFor($client, 'sodium_mg'));
        $this->assertSame(0, UserCriterion::where('user_id', $client->id)->where('source', CriterionSource::Nutritionist)->count());
        Notification::assertSentTo($profile->user, DatabaseNotification::class,
            fn (DatabaseNotification $n) => str_contains(json_encode($n->data, JSON_UNESCAPED_UNICODE), 'أنهى'));

        $this->get(route('site.my-nutritionist'))->assertSee('لم تختر أخصائياً بعد')->assertSee('انتهى الارتباط');
    }
}
