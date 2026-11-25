<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WeightLog;
use App\Services\Nutrition\Criteria\PersonalCriteria;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ConsultationFixtures;
use Tests\TestCase;

/**
 * The weekly weight on "يومي": a weighing per day that becomes the profile's weight (and so
 * the daily needs), the card's movement and "صار وقت توزن", the bell reminder a week after,
 * and a nutritionist's client whose weighing goes to the nutritionist instead.
 */
class WeighingTest extends TestCase
{
    use ConsultationFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo(CarbonImmutable::parse('2026-11-25 09:00', 'Asia/Damascus'));
    }

    private function person(): User
    {
        $user = User::forceCreate(['name' => 'سامر', 'email' => uniqid().'@example.com', 'password' => 'x-secret-1', 'is_active' => true, 'email_verified_at' => now()]);
        $user->profile()->create([
            'sex' => 'male', 'birth_year' => now()->year - 30, 'height_cm' => 178, 'weight_kg' => 80,
            'daily_life' => 'seated', 'work_days' => 7, 'goal' => 'lose', 'allergies' => [],
            'completed_at' => now(),
        ]);

        return $user;
    }

    private function energy(User $user): float
    {
        return app(PersonalCriteria::class)->for($user->fresh())->get('energy_kcal')->value;
    }

    public function test_a_weighing_becomes_the_profile_weight_and_the_needs_follow(): void
    {
        $user = $this->person();
        $before = $this->energy($user);

        $this->actingAs($user)->post(route('site.daily.weight'), ['weight_kg' => '78.4'])
            ->assertRedirect(route('site.daily'))
            ->assertSessionHas('status', 'سجّلنا وزنك: 78.4 كغ، وحدّثنا احتياجك عليه.');

        $this->assertSame(78.4, (float) $user->profile->fresh()->weight_kg);
        $this->assertLessThan($before, $this->energy($user));

        // Twice the same day: one row, the last weight.
        $this->post(route('site.daily.weight'), ['weight_kg' => '٧٨٫٢']);
        $this->assertSame(1, WeightLog::count());
        $this->assertSame(78.2, WeightLog::sole()->weight_kg);
    }

    public function test_an_odd_weight_is_refused(): void
    {
        $user = $this->person();

        foreach (['', 'ثمانين', '12', '450'] as $kg) {
            $this->actingAs($user)->from(route('site.daily'))->post(route('site.daily.weight'), ['weight_kg' => $kg])
                ->assertSessionHasErrors('weight_kg');
        }
        $this->assertSame(0, WeightLog::count());
        $this->assertSame(80.0, (float) $user->profile->fresh()->weight_kg);
    }

    public function test_the_card_shows_the_movement_and_asks_again_after_a_week(): void
    {
        $user = $this->person();

        $this->actingAs($user)->get(route('site.daily'))->assertOk()
            ->assertSee('وزنك')->assertSee('صار وقت توزن')->assertSee('من ملفك. سجّله أول مرة');

        foreach (['2026-11-04' => 80, '2026-11-11' => 79.4, '2026-11-18' => 78.8] as $day => $kg) {
            $this->travelTo(CarbonImmutable::parse($day.' 08:00', 'Asia/Damascus'));
            $this->post(route('site.daily.weight'), ['weight_kg' => $kg]);
        }

        // Seven days after the last weighing.
        $this->travelTo(CarbonImmutable::parse('2026-11-25 09:00', 'Asia/Damascus'));
        $this->get(route('site.daily'))->assertOk()
            ->assertSee('78.8')
            ->assertSee('نزلت')->assertSee('1.2')->assertSee('أسابيع')
            ->assertSee('آخر مرة من')
            ->assertSee('صار وقت توزن')
            ->assertSee('<polyline', false);

        $this->post(route('site.daily.weight'), ['weight_kg' => 78.5]);
        $this->get(route('site.daily'))->assertOk()->assertSee('وزنت اليوم')->assertDontSee('صار وقت توزن');
    }

    public function test_the_bell_reminds_once_a_week_after_the_last_weighing(): void
    {
        $user = $this->person();
        $this->actingAs($user);
        $weighReminders = fn () => $user->notifications()->where('data->topic', 'weight')->count();

        // Set up today: nothing yet.
        $this->get(route('home'))->assertOk();
        $this->assertSame(0, $weighReminders());

        $this->travelTo(CarbonImmutable::parse('2026-12-03 09:00', 'Asia/Damascus'));
        $this->get(route('home'))->assertOk();
        $this->get(route('site.meals'))->assertOk();
        $this->assertSame('صار وقت توزن', $user->notifications()->where('data->topic', 'weight')->sole()->data['title']);
        $this->assertSame(1, $weighReminders());

        // The next day: still one (a reminder from this week is not repeated).
        $this->travelTo(CarbonImmutable::parse('2026-12-04 09:00', 'Asia/Damascus'));
        $this->get(route('home'));
        $this->assertSame(1, $weighReminders());

        // Weighed: the next reminder waits a week from then.
        $this->post(route('site.daily.weight'), ['weight_kg' => 79]);
        $this->travelTo(CarbonImmutable::parse('2026-12-09 09:00', 'Asia/Damascus'));
        $this->get(route('home'));
        $this->assertSame(1, $weighReminders());
        $this->travelTo(CarbonImmutable::parse('2026-12-12 09:00', 'Asia/Damascus'));
        $this->get(route('home'));
        $this->assertSame(2, $weighReminders());
    }

    public function test_a_nutritionists_client_weighs_and_the_nutritionist_hears(): void
    {
        $client = $this->client();
        $nutritionist = $this->nutritionist();
        $this->linked($client, $nutritionist);

        $this->actingAs($client)->get(route('site.daily'))->assertOk()->assertSee('بيوصل وزنك لأخصائيّك');

        $this->post(route('site.daily.weight'), ['weight_kg' => 76.5])
            ->assertSessionHas('status', 'سجّلنا وزنك: 76.5 كغ، ووصل لأخصائيّك ليحدّث ملفك.');

        // The profile is the nutritionist's to change; the weighing is kept.
        $this->assertSame(78.0, (float) $client->profile->fresh()->weight_kg);
        $this->assertSame(76.5, WeightLog::sole()->weight_kg);
        $this->assertTrue($nutritionist->user->notifications()->where('data->title', 'سجّل سارة وزنه: 76.5 كغ')->exists());
    }
}
