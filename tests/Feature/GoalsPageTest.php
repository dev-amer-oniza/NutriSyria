<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MealFixtures;
use Tests\TestCase;

/** "Health goals" (the owner's decisions 1a 2a 3b 4a): the engine's own numbers, no meals per goal. */
class GoalsPageTest extends TestCase
{
    use MealFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->setUpMeals();
    }

    public function test_each_goal_has_its_section_with_the_engines_numbers(): void
    {
        $this->approvedMeal('مجدرة');

        $this->get(route('site.goals'))->assertOk()
            ->assertSeeInOrder(['خفض الوزن', 'بناء العضلات', 'السكري', 'صحة القلب', 'ألياف أكثر', 'وجبات متوازنة'])
            ->assertSee('id="weight-loss"', false)
            ->assertSee('ننقص 500 سعرة')
            ->assertSee('1,200 سعرة للمرأة و1,500 للرجل')
            ->assertSee('لا نملك قالباً خاصاً لبناء العضلات')
            ->assertSee('حتى 60 غ، وحتى 45 غ في سكري الحمل')
            ->assertSee('1,500 ملغ')
            ->assertSee('28 غ في اليوم')
            ->assertSee('هذا ليس بديلاً عن طبيبك')
            ->assertDontSee('ns-dish')                       // no meals under a goal (3b)
            ->assertDontSee('قريباً');
    }

    public function test_the_numbers_follow_the_config(): void
    {
        config(['nutrition_criteria.goal_adjustment.lose' => -400]);

        $this->get(route('site.goals'))->assertSee('ننقص 400 سعرة')->assertDontSee('ننقص 500 سعرة');
    }

    public function test_every_condition_is_listed_with_what_it_brings(): void
    {
        $this->get(route('site.goals'))->assertOk()
            ->assertSee('السكري والتمثيل الغذائي')
            ->assertSee('سكري الحمل')                        // women-only conditions too
            ->assertSee('غسيل الكلى')
            ->assertSee('DASH لضغط الدم:')
            ->assertSee('الحديد: 18 ملغ للمرأة و8 للرجل في اليوم.') // anaemia: no template, its own limit
            ->assertSee('ننصح بأخصائي');
    }

    public function test_the_next_step_depends_on_who_is_visiting(): void
    {
        $this->get(route('site.goals'))->assertSee(route('site.register'), false);

        $user = User::forceCreate(['name' => 'جديد', 'email' => 'new@example.com', 'password' => 'x-secret-1', 'is_active' => true, 'email_verified_at' => now()]);
        $this->actingAs($user)->get(route('site.goals'))->assertSee('أكمل ملفك')
            ->assertSee(route('site.onboarding.show', 'body'), false);

        $user->profile()->create([
            'sex' => 'male', 'birth_year' => now()->year - 30, 'height_cm' => 178, 'weight_kg' => 80,
            'activity_level' => 'moderate', 'goal' => 'lose', 'health_conditions' => [], 'allergies' => [], 'completed_at' => now(),
        ]);
        $this->actingAs($user->fresh())->get(route('site.goals'))->assertSee(route('site.meals', ['suits' => 1]), false);
    }

    public function test_the_home_chips_open_their_goal(): void
    {
        $this->get('/')->assertOk()->assertSee(route('site.goals').'#diabetes', false);
    }
}
