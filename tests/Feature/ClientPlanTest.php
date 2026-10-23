<?php

namespace Tests\Feature;

use App\Enums\CriterionSource;
use App\Filament\Clusters\Consultations\Resources\Clients\Pages\ClientPlan;
use App\Filament\Clusters\Consultations\Resources\Clients\Pages\ViewClient;
use App\Models\ConsultationRequest;
use App\Models\NutritionPlan;
use App\Notifications\SiteNotification;
use App\Services\Consultations\ClientWorkspace;
use App\Services\Nutrition\Criteria\PersonalCriteria;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\Concerns\ConsultationFixtures;
use Tests\TestCase;

/**
 * The nutritionist's nutrition plan, phase 1 (decisions 1a 2a 3a). The client (fixtures) is a
 * woman of 34, 165 cm, 78 kg, lightly active, with diabetes, aiming to lose weight: Mifflin
 * 1480 at rest, 2035 a day, 1540 after the standard deficit; diabetes shares 45/20/35.
 */
class ClientPlanTest extends TestCase
{
    use ConsultationFixtures;
    use RefreshDatabase;

    private ConsultationRequest $link;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Notification::fake();

        $reem = $this->nutritionist();
        $this->link = $this->linked($this->client(), $reem);
        $this->actingAs($reem->user);
        Filament::setCurrentPanel('admin');
    }

    private function plan(): \Livewire\Features\SupportTesting\Testable
    {
        return Livewire::test(ClientPlan::class, ['record' => $this->link->getKey()]);
    }

    private function value(string $criterion): ?float
    {
        return app(PersonalCriteria::class)->for($this->link->user->fresh())->get($criterion)?->value;
    }

    public function test_a_new_plan_starts_from_mifflin_the_goal_and_the_template_shares(): void
    {
        $this->plan()->assertOk()
            ->assertSet('data.equation', 'mifflin')
            ->assertSet('data.activity', 'light')
            ->assertSet('data.adjustment', -500)
            ->assertSet('data.carbs_pct', 45)
            ->assertSee('1,480')       // BMR
            ->assertSee('2,035')       // daily need
            ->assertSee('1,540')       // final calories
            ->assertSee('2.75 ل');      // 35 ml × 78 kg
    }

    public function test_saving_sets_the_clients_calories_and_macros_and_tells_them_once(): void
    {
        $this->plan()
            ->set('data.adjustment', -750)
            ->set('data.carbs_pct', 40)->set('data.protein_pct', 30)->set('data.fat_pct', 30)
            ->set('data.fluids_ml', 2500)
            ->call('save')
            ->assertNotified('حُفظت الخطة');

        // 2035 − 750 = 1290: 129 g carbs, 97 g protein, 43 g fat.
        $this->assertSame([1290.0, 129.0, 97.0, 43.0], array_map(fn ($c) => $this->value($c), ['energy_kcal', 'carbohydrate_g', 'protein_g', 'fat_g']));
        $energy = app(PersonalCriteria::class)->for($this->link->user->fresh())->get('energy_kcal');
        $this->assertSame(CriterionSource::Nutritionist, $energy->source);
        $this->assertSame(ClientWorkspace::PLAN_NOTE, $energy->note);

        $plan = NutritionPlan::firstOrFail();
        $this->assertSame([-750, 2500], [$plan->adjustment_kcal, $plan->fluids_ml]);
        $this->assertCount(1, Notification::sent($this->link->user, SiteNotification::class)->filter(fn (SiteNotification $n) => str_starts_with($n->title, 'حدّث')));

        $this->get(ViewClient::getUrl(['record' => $this->link]))->assertOk()
            ->assertSee('خطة التغذية')
            ->assertSee('129 / 97 / 43 غ');
    }

    public function test_shares_that_do_not_make_100_are_not_saved(): void
    {
        $this->plan()
            ->set('data.carbs_pct', 60)
            ->assertSee('مجموع النسب 115%، ويجب أن يكون 100%.')
            ->call('save')
            ->assertNotified('لم تُحفظ الخطة');

        $this->assertSame(0, NutritionPlan::count());
        $this->assertSame(CriterionSource::Default, app(PersonalCriteria::class)->for($this->link->user)->get('energy_kcal')->source);
    }

    public function test_katch_mcardle_asks_for_the_body_fat_share(): void
    {
        $this->plan()->set('data.equation', 'katch_mcardle')->call('save')->assertHasFormErrors(['body_fat_pct' => 'required']);

        $this->plan()->set('data.equation', 'katch_mcardle')->set('data.body_fat_pct', 35)->call('save')->assertHasNoFormErrors();
        $this->assertSame(35.0, NutritionPlan::firstOrFail()->body_fat_pct);
    }

    public function test_for_dialysis_the_fluids_must_be_typed(): void
    {
        $this->link->user->profile->update(['health_conditions' => ['dialysis']]);

        $this->plan()->assertSee('توصية الطبيب')->call('save')->assertHasFormErrors(['fluids_ml' => 'required']);
        $this->plan()->set('data.fluids_ml', 1000)->call('save')->assertHasNoFormErrors();
    }

    public function test_a_new_weight_keeps_the_plan_in_step_and_removing_it_restores_the_template(): void
    {
        $workspace = app(ClientWorkspace::class);
        $nutritionist = $this->link->nutritionist;
        $workspace->savePlan($nutritionist, $this->link, ['equation' => 'mifflin', 'body_fat_pct' => null, 'activity' => 'light', 'adjustment' => -500, 'carbs_pct' => 45, 'protein_pct' => 20, 'fat_pct' => 35, 'fluids_ml' => null]);
        $this->assertSame(1540.0, $this->value('energy_kcal'));

        $profile = $this->link->user->profile;
        $workspace->updateProfile($nutritionist, $this->link, [
            'sex' => 'female', 'birth_year' => $profile->birth_year, 'height_cm' => 165, 'weight_kg' => 70,
            'activity_level' => 'light', 'goal' => 'lose', 'health_conditions' => ['diabetes'], 'allergies' => [],
        ]);
        $this->assertSame(1430.0, $this->value('energy_kcal'));   // 80 kcal less at rest × 1.375

        $workspace->removePlan($nutritionist, $this->link);
        $this->assertSame(0, NutritionPlan::count());
        $this->assertSame(CriterionSource::Default, app(PersonalCriteria::class)->for($this->link->user->fresh())->get('energy_kcal')->source);
    }

    public function test_someone_elses_client_has_no_plan_page(): void
    {
        $this->actingAs($this->nutritionist('omar@example.com')->user);

        $this->get(ClientPlan::getUrl(['record' => $this->link]))->assertNotFound();
    }
    public function test_the_standard_split_fills_five_meals_with_their_grams(): void
    {
        $page = $this->plan()->callAction(TestAction::make('preset_standard')->schemaComponent('mealsSection', 'form'));

        $meals = array_values($page->get('data.meals'));
        $this->assertSame(['breakfast', 'snack', 'lunch', 'snack', 'dinner'], array_column($meals, 'type'));
        $this->assertSame([25, 10, 35, 10, 20], array_column($meals, 'share_pct'));
        // 1540 kcal, diabetes 45/20/35: 173 g carbs, 77 g protein, 60 g fat, shared by the meals' calories.
        $this->assertSame(173, array_sum(array_column($meals, 'carbs_g')));
        $this->assertSame(77, array_sum(array_column($meals, 'protein_g')));
        $this->assertSame(60, array_sum(array_column($meals, 'fat_g')));
        $page->assertSee('باقي للتوزيع')->assertSee('غداء');
    }

    public function test_what_is_left_shows_as_the_nutritionist_types_and_only_the_shares_block_saving(): void
    {
        $page = $this->plan()->callAction(TestAction::make('preset_three')->schemaComponent('mealsSection', 'form'));
        $lunch = array_keys($page->get('data.meals'))[1];

        $page->set("data.meals.$lunch.protein_g", $page->get("data.meals.$lunch.protein_g") - 20)
            ->assertSee('لم تتطابق غرامات الوجبات مع المجموع: البروتين باقي 20 غ.')
            ->call('save')
            ->assertNotified('حُفظت الخطة');                                   // grams left: a warning (1a)

        $this->assertCount(3, NutritionPlan::firstOrFail()->meals);

        $page->set("data.meals.$lunch.share_pct", 30)
            ->assertSee('مجموع نسب الوجبات 90%، ويجب أن يكون 100%.')
            ->call('save')
            ->assertNotified('لم تُحفظ الخطة');                                // shares must make 100
    }

    public function test_saved_meals_come_back_by_time_and_show_in_the_client_file(): void
    {
        app(ClientWorkspace::class)->savePlan($this->link->nutritionist, $this->link, [
            'equation' => 'mifflin', 'body_fat_pct' => null, 'activity' => 'light', 'adjustment' => -500,
            'carbs_pct' => 45, 'protein_pct' => 20, 'fat_pct' => 35, 'fluids_ml' => null,
            'meals' => [
                ['type' => 'dinner', 'time' => '20:00', 'share_pct' => 40, 'carbs_g' => 70, 'protein_g' => 31, 'fat_g' => 24],
                ['type' => 'other', 'label' => 'بعد التمرين', 'time' => '10:30', 'share_pct' => 10, 'carbs_g' => 17, 'protein_g' => 8, 'fat_g' => 6],
                ['type' => 'lunch', 'time' => '14:00', 'share_pct' => 50, 'carbs_g' => 86, 'protein_g' => 38, 'fat_g' => 30],
            ],
        ]);

        $this->assertSame(['other', 'lunch', 'dinner'], array_column(NutritionPlan::firstOrFail()->meals, 'type'));
        $this->assertSame('بعد التمرين', NutritionPlan::firstOrFail()->meals[0]['label']);

        $this->get(ViewClient::getUrl(['record' => $this->link]))->assertOk()
            ->assertSeeInOrder(['10:30', 'بعد التمرين', '14:00', 'غداء', '770', '20:00', 'عشاء']);   // lunch: 50% of 1540

        $this->plan()->assertSet('data.meals', fn (array $meals) => count($meals) === 3);
    }
}
