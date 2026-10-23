<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\ConsultationFixtures;
use Tests\TestCase;

/** The body mass index card on "your daily needs" and "my criteria" (decisions 1a 2a 3a). */
class BmiDisplayTest extends TestCase
{
    use ConsultationFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Notification::fake();
    }

    private function person(array $profile = []): User
    {
        $user = User::forceCreate(['name' => 'سامر', 'email' => uniqid().'@example.com', 'password' => 'x-secret-1', 'is_active' => true, 'email_verified_at' => now()]);
        $user->profile()->create($profile + [
            'sex' => 'male', 'birth_year' => now()->year - 30, 'height_cm' => 178, 'weight_kg' => 80,
            'activity_level' => 'moderate', 'goal' => 'maintain', 'health_conditions' => [], 'allergies' => [], 'completed_at' => now(),
        ]);

        return $user->fresh();
    }

    public function test_the_daily_needs_page_shows_the_index_its_class_and_the_healthy_range(): void
    {
        $this->actingAs($this->person())->get(route('site.onboarding.done'))->assertOk()
            ->assertSee('مؤشر كتلة الجسم')
            ->assertSee('25.2')
            ->assertSee('أعلى من الطبيعي')
            ->assertDontSee('زيادة وزن')                      // the clinical word is for the nutritionist
            ->assertSee('بين <span class="ns-num">59</span> و<span class="ns-num">78</span> كغ', false)
            ->assertDontSee('لا ننصحك');
    }

    public function test_losing_weight_while_underweight_is_warned_about_with_a_way_to_change_the_goal(): void
    {
        $this->actingAs($this->person(['weight_kg' => 55, 'goal' => 'lose']));

        $this->get(route('site.criteria'))->assertOk()
            ->assertSee('أقل من الطبيعي')
            ->assertSee('لا ننصحك بإنقاص الوزن')
            ->assertSee(route('site.onboarding.show', 'activity'), false);
    }

    public function test_a_managed_person_is_warned_but_sent_to_their_nutritionist_not_to_the_goal_form(): void
    {
        $client = $this->client();                                   // diabetes, 78 kg, 165 cm, goal lose
        $client->profile->update(['weight_kg' => 48]);
        $this->linked($client, $this->nutritionist());

        $this->actingAs($client->fresh())->get(route('site.criteria'))->assertOk()
            ->assertSee('لا ننصحك بإنقاص الوزن')
            ->assertDontSee('غيّر هدفك');
    }

    public function test_in_pregnancy_the_number_shows_without_a_class(): void
    {
        $this->actingAs($this->person(['sex' => 'female', 'weight_kg' => 95, 'goal' => 'gain', 'health_conditions' => ['pregnant_or_breastfeeding']]));

        $this->get(route('site.criteria'))->assertOk()
            ->assertSee('30.0')
            ->assertSee('في الحمل والرضاعة لا يعبّر المؤشر عن حالتك')
            ->assertDontSee('أعلى بكثير من الطبيعي')
            ->assertDontSee('قد لا تناسبك زيادة الوزن');
    }
}
