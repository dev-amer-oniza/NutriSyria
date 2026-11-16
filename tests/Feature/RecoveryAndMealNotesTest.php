<?php

namespace Tests\Feature;

use App\Models\FoodNutrientValue;
use App\Models\User;
use App\Services\Site\HealthGoalGuide;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\ConsultationFixtures;
use Tests\Concerns\MealFixtures;
use Tests\TestCase;

/**
 * The owner's decisions on the hummus review: the stages of a recovery are told by adding and
 * removing "عدم تحمّل الدسم", with hints and a reminder every four weeks (c), and a dish usually
 * eaten with bread says its values are for the plate alone (4b).
 */
class RecoveryAndMealNotesTest extends TestCase
{
    use ConsultationFixtures;
    use MealFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function withConditions(array $conditions): User
    {
        $user = $this->client();
        $user->profile->update(['health_conditions' => $conditions]);

        return $user;
    }

    private function bellTitles(User $user): array
    {
        return DB::table('notifications')->where('notifiable_id', $user->id)->pluck('data')
            ->map(fn ($d) => json_decode($d, true)['title'])->all();
    }

    public function test_a_passing_condition_is_asked_about_every_four_weeks(): void
    {
        $user = $this->withConditions(['gallbladder_removed', 'fat_intolerance']);

        // The first visit starts the clock, with no notice yet.
        $this->actingAs($user)->get(route('home'))->assertOk();
        $this->assertNotNull($user->profile->fresh()->recovery_reminded_at);
        $this->assertSame([], $this->bellTitles($user));

        $this->travel(27)->days();
        $this->get(route('home'))->assertOk();
        $this->assertSame([], $this->bellTitles($user));

        $this->travel(2)->days();
        $this->get(route('home'))->assertOk();
        $this->assertSame(['هل ما زال عندك «عدم تحمّل الدسم»؟'], $this->bellTitles($user));

        // Once a day is enough: the next visit asks nothing new.
        $this->get(route('home'));
        $this->assertCount(1, $this->bellTitles($user));
    }

    public function test_removing_the_condition_stops_the_reminders(): void
    {
        $user = $this->withConditions(['fat_intolerance']);
        $this->actingAs($user)->get(route('home'));

        $user->profile->update(['health_conditions' => ['gallbladder_removed']]);
        $this->get(route('home'));
        $this->assertNull($user->profile->fresh()->recovery_reminded_at);

        $this->travel(60)->days();
        $this->get(route('home'));
        $this->assertSame([], $this->bellTitles($user));
    }

    public function test_a_lasting_condition_is_never_asked_about(): void
    {
        $user = $this->withConditions(['gallbladder_removed', 'diabetes']);
        $this->actingAs($user)->get(route('home'));
        $this->travel(60)->days();
        $this->get(route('home'));

        $this->assertNull($user->profile->fresh()->recovery_reminded_at);
        $this->assertSame([], $this->bellTitles($user));
    }

    public function test_the_hints_tell_how_to_mark_a_stage_of_recovery(): void
    {
        $user = $this->client();
        $this->actingAs($user)->get(route('site.onboarding.health'))->assertOk()
            ->assertSee('اختر معها «عدم تحمّل الدسم»، وأزلها حين ترتاح.')
            ->assertSee('نذكّرك بها كل أربعة أسابيع.');

        // The goals page keeps each template's description, with the hint under it.
        $gallbladder = collect(app(HealthGoalGuide::class)->conditions())->flatMap(fn ($g) => $g['conditions'])
            ->firstWhere('label', 'استئصال المرارة');
        $this->assertNotSame('', $gallbladder['text']);
        $this->assertStringContainsString('عدم تحمّل الدسم', $gallbladder['hint']);
    }

    public function test_a_dish_eaten_with_bread_says_its_values_are_for_the_plate(): void
    {
        $this->setUpMeals();
        $hummus = $this->approvedMeal('حمص بطحينة', ['eaten_with_bread' => true]);
        $stew = $this->approvedMeal('ملوخية');

        $this->get(route('site.meals.show', $hummus))->assertOk()->assertSee('تؤكل عادةً مع الخبز، والأرقام هنا للصحن وحده');
        $this->get(route('site.meals.show', $stew))->assertOk()->assertDontSee('تؤكل عادةً مع الخبز');
    }

    public function test_the_bread_note_says_what_one_loaf_adds(): void
    {
        $this->setUpMeals();
        $bread = $this->ingredient('خبز عربي');
        foreach (['energy_kcal' => 275, 'carbohydrate_g' => 55.7] as $code => $amount) {
            FoodNutrientValue::create(['food_source_record_id' => $bread->id, 'nutrient_id' => $this->nutrients[$code]->id, 'amount_per_100g' => $amount]);
        }
        $hummus = $this->approvedMeal('حمص بطحينة', ['eaten_with_bread' => true]);

        // 60 g: 275 × 0.6 = 165 kcal, 55.7 × 0.6 ≈ 33 g.
        $this->get(route('site.meals.show', $hummus))->assertOk()
            ->assertSeeInOrder(['تؤكل عادةً مع الخبز', '60', '165', '33']);
    }
}
