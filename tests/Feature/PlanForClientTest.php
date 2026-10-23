<?php

namespace Tests\Feature;

use App\Models\ConsultationRequest;
use App\Services\Consultations\ClientWorkspace;
use App\Services\Consultations\ConsultationService;
use App\Services\Nutrition\Criteria\PersonalCriteria;
use App\Services\Nutrition\Plan\NutritionPlans;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\ConsultationFixtures;
use Tests\Concerns\MealFixtures;
use Tests\TestCase;

/**
 * Plan phase 3 (decisions 1a 2a 3a 4a): dishes judged against the plan's lunch, and the plan
 * shown to the client. The client (fixtures) has 1540 kcal from the plan below; the lunch is
 * 35% = 539 kcal with 50 g carbs. The mujaddara serving (250 g) has 415 kcal and 62 g carbs.
 */
class PlanForClientTest extends TestCase
{
    use ConsultationFixtures;
    use MealFixtures;
    use RefreshDatabase;

    private ConsultationRequest $link;

    private const PLAN = [
        'equation' => 'mifflin', 'body_fat_pct' => null, 'activity' => 'light', 'adjustment' => -500,
        'carbs_pct' => 45, 'protein_pct' => 20, 'fat_pct' => 35, 'fluids_ml' => 2500,
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Notification::fake();
        $this->setUpMeals();

        $this->link = $this->linked($this->client(), $this->nutritionist());
        $this->link->user->profile->update(['health_conditions' => [], 'allergies' => []]);
    }

    private function savePlan(array $meals): void
    {
        app(ClientWorkspace::class)->savePlan($this->link->nutritionist, $this->link, self::PLAN + ['meals' => $meals]);
    }

    private function meals(): array
    {
        return [
            ['type' => 'breakfast', 'time' => '08:00', 'share_pct' => 30, 'carbs_g' => 60, 'protein_g' => 23, 'fat_g' => 18],
            ['type' => 'lunch', 'time' => '14:00', 'share_pct' => 35, 'carbs_g' => 50, 'protein_g' => 30, 'fat_g' => 21],
            ['type' => 'dinner', 'time' => '20:00', 'share_pct' => 35, 'carbs_g' => 63, 'protein_g' => 24, 'fat_g' => 21],
        ];
    }

    public function test_the_reference_meal_is_the_first_lunch_with_its_own_grams(): void
    {
        $meal = NutritionPlans::referenceMeal($this->meals(), 1540);

        $this->assertSame(['غداء', '14:00', 0.35, 539], [$meal['name'], $meal['time'], $meal['share'], $meal['kcal']]);
        $this->assertSame(['energy_kcal' => 539.0, 'carbohydrate_g' => 50.0, 'protein_g' => 30.0, 'fat_g' => 21.0], $meal['targets']);
        $this->assertNull(NutritionPlans::referenceMeal([['type' => 'snack', 'time' => '10:00', 'share_pct' => 100]], 1540));
    }

    public function test_dishes_are_judged_against_the_plan_lunch(): void
    {
        $this->savePlan($this->meals());
        $meal = $this->approvedMeal('مجدرة');

        $this->actingAs($this->link->user->fresh())->get(route('site.meals.show', $meal))->assertOk()
            ->assertSee('مقارنةً بغدائك في خطة أخصائيّك (14:00، 539 سعرة)')
            ->assertSee('وغداؤك في خطتك 50 غ');                               // 62 g carbs against the lunch's 50 g
    }

    public function test_without_a_lunch_the_usual_share_of_the_day_applies(): void
    {
        $this->savePlan([['type' => 'breakfast', 'time' => '08:00', 'share_pct' => 50, 'carbs_g' => 87, 'protein_g' => 39, 'fat_g' => 30],
            ['type' => 'dinner', 'time' => '20:00', 'share_pct' => 50, 'carbs_g' => 86, 'protein_g' => 38, 'fat_g' => 30]]);

        $sheet = app(PersonalCriteria::class)->for($this->link->user->fresh());
        $this->assertNull($sheet->meal);
        $this->assertSame(0.3, $sheet->mealShare);
    }

    public function test_the_client_sees_the_plans_results_only(): void
    {
        $this->savePlan($this->meals());

        $this->actingAs($this->link->user->fresh())->get(route('site.criteria'))->assertOk()
            ->assertSee('خطتك من أخصائيّك')
            ->assertSeeInOrder(['1,540', '173', '77', '60', '2.5'])            // kcal, carbs, protein, fat (g), fluids (l)
            ->assertSeeInOrder(['08:00', 'فطور', '14:00', 'غداء', '539', '20:00', 'عشاء'])
            ->assertSee('غداء: <span class="ns-num">50</span>', false)       // the criteria list shows the lunch's own grams
            ->assertDontSee('الأيض')                                           // no BMR, equation or change (3a)
            ->assertDontSee('Mifflin')
            ->assertDontSee('−500');

        $this->get(route('site.onboarding.done'))->assertSee('غداؤك في خطة أخصائيّك');
    }

    public function test_when_the_link_ends_the_plan_goes_and_the_usual_share_returns(): void
    {
        $this->savePlan($this->meals());
        app(ConsultationService::class)->end($this->link->user, $this->link);

        $client = $this->link->user->fresh();
        $this->assertNull(app(PersonalCriteria::class)->for($client)->meal);
        $this->actingAs($client)->get(route('site.criteria'))->assertOk()->assertDontSee('خطتك من أخصائيّك');
    }
}
