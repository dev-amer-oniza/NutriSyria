<?php

namespace Tests\Feature;

use App\Filament\Clusters\Consultations\Resources\Clients\Pages\ViewClient;
use App\Http\Middleware\EnsureProfileUnlocked;
use App\Models\User;
use App\Notifications\SiteNotification;
use App\Services\Consultations\ConsultationService;
use App\Services\Nutrition\Criteria\PersonalCriteria;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\Concerns\ConsultationFixtures;
use Tests\TestCase;

class SiteBellAndProfileLockTest extends TestCase
{
    use ConsultationFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_being_accepted_rings_the_bell_and_sends_no_email(): void
    {
        Mail::fake();
        $link = $this->linked($client = $this->client(), $this->nutritionist());

        Mail::assertNothingSent();
        Mail::assertNothingQueued();

        $this->actingAs($client)->get(route('home'))
            ->assertOk()
            ->assertSee('ns-bell__count', false);

        $feed = $this->getJson(route('site.notifications'))->assertOk()->json();
        $this->assertSame(1, $feed['unread']);
        $this->assertSame('success', $feed['items'][0]['kind']);
        $this->assertSame('قبل د. reem@example.com طلبك', $feed['items'][0]['title']);
        $this->assertFalse($feed['items'][0]['read']);

        // Opening a notice marks it read and goes to its page.
        $this->get($feed['items'][0]['open'])->assertRedirect('/account/nutritionist');
        $this->assertSame(0, $this->getJson(route('site.notifications'))->json('unread'));
    }

    public function test_the_bell_shows_only_my_site_notices(): void
    {
        $reem = $this->nutritionist();
        $link = $this->linked($client = $this->client(), $reem);
        $other = $this->client('lina@example.com');

        // The nutritionist's panel notice (format "filament") stays out of the site bell.
        $this->actingAs($reem->user)->getJson(route('site.notifications'))->assertJson(['unread' => 0, 'items' => []]);

        $id = $client->notifications()->first()->id;
        $this->actingAs($other)->get(route('site.notifications.open', $id))->assertNotFound();
        $this->assertNull($client->notifications()->first()->read_at);
    }

    public function test_mark_all_read(): void
    {
        $reem = $this->nutritionist();
        $link = $this->linked($client = $this->client(), $reem);
        app(\App\Services\Consultations\ClientWorkspace::class)->setCriterion($reem->user, $link, 'sodium_mg', 1500);

        $this->actingAs($client)->getJson(route('site.notifications'))->assertJson(['unread' => 2]);
        $this->postJson(route('site.notifications.read'))->assertJson(['unread' => 0]);
        $this->getJson(route('site.notifications'))->assertJson(['unread' => 0]);
    }

    public function test_a_guest_has_no_bell(): void
    {
        $this->get(route('home'))->assertOk()->assertDontSee('ns-bell', false);
        $this->get(route('site.notifications'))->assertRedirect(route('site.login'));
    }

    public function test_a_linked_person_cannot_change_their_profile_criteria_or_health_data(): void
    {
        $link = $this->linked($client = $this->client(), $this->nutritionist());
        $before = $client->profile->fresh()->attributesToArray();
        $this->actingAs($client);

        $this->get(route('site.onboarding.show', 'body'))
            ->assertRedirect(route('site.criteria'))->assertSessionHas('error', EnsureProfileUnlocked::MESSAGE);
        $this->post(route('site.onboarding.body'), ['sex' => 'female', 'birth_year' => 1990, 'height_cm' => 165, 'weight_kg' => 60])
            ->assertSessionHas('error');
        $this->post(route('site.onboarding.activity'), ['activity_level' => 'very', 'goal' => 'gain'])->assertSessionHas('error');
        $this->post(route('site.onboarding.health'), ['conditions' => [], 'allergies' => []])->assertSessionHas('error');
        $this->delete(route('site.health.destroy'))->assertSessionHas('error');
        $this->put(route('site.criteria.update', 'fiber_g'), ['value' => 40])->assertSessionHas('error');
        $this->delete(route('site.criteria.reset', 'fiber_g'))->assertSessionHas('error');
        $this->put(route('site.template.update'), ['template' => 'balanced'])->assertSessionHas('error');
        $this->delete(route('site.template.reset'))->assertSessionHas('error');

        $this->assertSame($before, $client->profile->fresh()->attributesToArray());
        $this->assertSame(['diabetes'], $client->profile->fresh()->health_conditions);
        $this->assertFalse(app(PersonalCriteria::class)->lockedFor($client, 'fiber_g'));
        $this->assertSame('diabetes', app(PersonalCriteria::class)->for($client)->template['key']);

        $this->get(route('site.criteria'))->assertOk()
            ->assertSee(EnsureProfileUnlocked::MESSAGE)
            ->assertDontSee('احذف بياناتي الصحية')
            ->assertDontSee('عدّل بياناتي')
            ->assertSee('يغيّره أخصائيّك فقط');
    }

    public function test_before_acceptance_and_after_the_link_ends_the_person_edits_freely(): void
    {
        $profile = $this->nutritionist();
        $request = $this->pending($client = $this->client(), $profile);
        $this->actingAs($client);

        $this->put(route('site.criteria.update', 'fiber_g'), ['value' => 40])->assertSessionHasNoErrors()->assertSessionMissing('error');

        app(ConsultationService::class)->accept($profile->user, $request);
        $this->put(route('site.criteria.update', 'fiber_g'), ['value' => 35])->assertSessionHas('error');

        // Ending stays open to the person, and unlocks everything.
        $this->post(route('site.consultations.end', $request->id))->assertRedirect(route('site.my-nutritionist'));
        $this->get(route('site.onboarding.show', 'body'))->assertOk();
        $this->delete(route('site.health.destroy'))->assertSessionMissing('error');
        $this->assertNull($client->profile->fresh()->health_conditions);
    }

    public function test_the_nutritionist_updates_the_profile_and_the_person_is_told(): void
    {
        $reem = $this->nutritionist();
        $link = $this->linked($client = $this->client(), $reem);
        $this->actingAs($reem->user);
        Filament::setCurrentPanel('admin');

        Livewire::test(ViewClient::class, ['record' => $link->id])
            ->callAction('editProfile', [
                'sex' => 'female', 'birth_year' => now()->year - 34, 'height_cm' => 165, 'weight_kg' => 72,
                'activity_level' => 'moderate', 'goal' => 'lose',
                'health_conditions' => ['diabetes', 'hypertension'], 'allergies' => ['gluten'],
            ])
            ->assertHasNoActionErrors()
            ->assertNotified('حُفظت البيانات، وأُعيد حساب معاييره.');

        $profile = $client->profile->fresh();
        $this->assertSame(72.0, (float) $profile->weight_kg);
        $this->assertSame('moderate', $profile->activity_level->value);
        $this->assertSame(['diabetes', 'hypertension'], $profile->health_conditions);
        $this->assertSame(['gluten'], $profile->allergies);
        $this->assertSame(1, $client->notifications()->where('data->title', 'like', 'حدّث%')->count());
    }

    public function test_women_only_conditions_are_dropped_for_men(): void
    {
        $reem = $this->nutritionist();
        $link = $this->linked($client = $this->client(), $reem);

        app(\App\Services\Consultations\ClientWorkspace::class)->updateProfile($reem->user, $link, [
            'sex' => 'male', 'birth_year' => 1990, 'height_cm' => 178, 'weight_kg' => 80,
            'activity_level' => 'light', 'goal' => 'maintain',
            'health_conditions' => ['diabetes', 'pregnant_or_breastfeeding'], 'allergies' => [],
        ]);

        $this->assertSame(['diabetes'], $client->profile->fresh()->health_conditions);
    }

    public function test_site_notifications_are_database_only(): void
    {
        $link = $this->linked($this->client(), $this->nutritionist());

        $this->assertSame(['database'], SiteNotification::accepted($link)->via($link->user));
        $this->assertSame('site', SiteNotification::accepted($link)->toDatabase($link->user)['format']);
    }
}
