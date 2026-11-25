<?php

namespace Tests\Feature;

use App\Auth\Access;
use App\Enums\FoodRequestStatus;
use App\Enums\MealType;
use App\Filament\Clusters\Kitchen\Resources\FoodRequests\Pages\ListFoodRequests;
use App\Models\FoodRequest;
use App\Models\MealLog;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use App\Services\Nutrition\Criteria\PersonalCriteria;
use Carbon\CarbonImmutable;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Concerns\MealFixtures;
use Tests\TestCase;

/**
 * "يومي": "أكلتها" from a meal's page, what is left of the day, "ما لقيتها؟" sent to the
 * admin and answered, and the dishes that suit this hour's meal. The mujaddara plate (250 g)
 * is 415 kcal.
 */
class DailyLogTest extends TestCase
{
    use MealFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->setUpMeals();
        // 20:30 in Damascus: dinner time.
        $this->travelTo(CarbonImmutable::parse('2026-11-25 20:30', 'Asia/Damascus'));
    }

    private function person(string $name = 'سامر'): User
    {
        $user = User::forceCreate(['name' => $name, 'email' => uniqid().'@example.com', 'password' => 'x-secret-1', 'is_active' => true, 'email_verified_at' => now()]);
        $user->profile()->create([
            'sex' => 'male', 'birth_year' => now()->year - 30, 'height_cm' => 178, 'weight_kg' => 80,
            'daily_life' => 'seated', 'work_days' => 7, 'goal' => 'maintain', 'allergies' => [],
            'completed_at' => now(),
        ]);

        return $user;
    }

    private function need(User $user): float
    {
        return app(PersonalCriteria::class)->for($user)->get('energy_kcal')->value;
    }

    public function test_a_guest_is_sent_to_sign_in(): void
    {
        $this->get(route('site.daily'))->assertRedirect(route('site.login'));
        $meal = $this->approvedMeal('مجدرة');
        $this->post(route('site.daily.store', $meal), ['amount' => 1, 'meal_type' => 'lunch'])->assertRedirect(route('site.login'));
        $this->get(route('site.meals.show', $meal))->assertDontSee('أكلتها');
    }

    public function test_half_a_plate_is_logged_with_its_values_and_counted_from_the_day(): void
    {
        $user = $this->person();
        $meal = $this->approvedMeal('مجدرة');

        $this->actingAs($user)->get(route('site.meals.show', $meal))->assertOk()
            ->assertSee('أكلتها')
            ->assertSee('قديش أكلت من مجدرة؟')
            ->assertSee('نص الصحن')
            // The hour's meal is chosen first: dinner at 20:30.
            ->assertSee('value="dinner" checked', false);

        $this->post(route('site.daily.store', $meal), ['amount' => 0.5, 'meal_type' => 'dinner'])
            ->assertRedirect(route('site.daily'));

        $entry = MealLog::sole();
        $this->assertSame(MealType::Dinner, $entry->meal_type);
        $this->assertSame('2026-11-25', $entry->eaten_on->toDateString());
        $this->assertEqualsWithDelta(207.5, $entry->energy_kcal, 0.1);
        $this->assertEqualsWithDelta(7.5, $entry->protein_g, 0.1);

        $left = (int) round($this->need($user) - 207.5);
        $this->get(route('site.daily'))->assertOk()
            ->assertSee('باقيلك اليوم')
            ->assertSee(number_format($left))
            ->assertSee('مجدرة')
            ->assertSee('نص الصحن')
            ->assertSee('208');
    }

    public function test_a_kept_value_does_not_change_when_the_recipe_does(): void
    {
        $user = $this->person();
        $meal = $this->approvedMeal('مجدرة');
        $this->actingAs($user)->post(route('site.daily.store', $meal), ['amount' => 1, 'meal_type' => 'lunch']);

        DB::table('food_nutrient_values')->where('product_id', $meal->id)->update(['amount_per_100g' => 999]);

        $this->assertEqualsWithDelta(415, MealLog::sole()->energy_kcal, 0.1);
    }

    public function test_an_odd_amount_or_a_hidden_meal_is_refused(): void
    {
        $user = $this->person();
        $meal = $this->approvedMeal('مجدرة');
        $hidden = Product::create(['brand_id' => $this->brand->id, 'name' => 'بلا اعتماد']);

        $this->actingAs($user)->from(route('site.meals.show', $meal))
            ->post(route('site.daily.store', $meal), ['amount' => 0.3, 'meal_type' => 'lunch'])
            ->assertSessionHasErrors('amount');
        $this->post(route('site.daily.store', $meal), ['amount' => 1, 'meal_type' => 'brunch'])->assertSessionHasErrors('meal_type');
        $this->post(route('site.daily.store', $hidden), ['amount' => 1, 'meal_type' => 'lunch'])->assertNotFound();

        $this->assertSame(0, MealLog::count());
    }

    public function test_only_my_own_entry_can_be_taken_off(): void
    {
        $me = $this->person();
        $other = $this->person('ليلى');
        $meal = $this->approvedMeal('مجدرة');
        $this->actingAs($other)->post(route('site.daily.store', $meal), ['amount' => 1, 'meal_type' => 'lunch']);
        $theirs = MealLog::sole();

        $this->actingAs($me)->delete(route('site.daily.destroy', $theirs->id))->assertNotFound();
        $this->assertTrue($theirs->exists());

        $this->actingAs($other)->delete(route('site.daily.destroy', $theirs->id))->assertRedirect();
        $this->assertSame(0, MealLog::count());
    }

    public function test_a_past_day_of_the_week_shows_its_own_meals_and_a_future_one_is_today(): void
    {
        $user = $this->person();
        $meal = $this->approvedMeal('مجدرة');
        $this->actingAs($user)->post(route('site.daily.store', $meal), ['amount' => 1, 'meal_type' => 'lunch']);

        $this->travelTo(CarbonImmutable::parse('2026-11-26 09:00', 'Asia/Damascus'));

        $this->get(route('site.daily'))->assertOk()->assertSee('لسا ما سجّلت شي اليوم')
            ->assertSee('الخميس 26 تشرين التاني');
        $this->get(route('site.daily', ['date' => '2026-11-25']))->assertOk()
            ->assertSee('مجدرة')->assertSee('رجوع لليوم')->assertDontSee('شو بيناسبك');
        $this->get(route('site.daily', ['date' => '2026-12-30']))->assertOk()->assertSee('لسا ما سجّلت شي اليوم');
        $this->get(route('site.daily', ['date' => 'garbage']))->assertOk();
    }

    public function test_the_weeks_go_back_as_far_as_the_first_meal_logged(): void
    {
        $user = $this->person();
        $meal = $this->approvedMeal('مجدرة');

        // Nothing logged yet: no week to go back to, and no week after this one.
        $this->actingAs($user)->get(route('site.daily'))->assertOk()
            ->assertDontSee('rel="prev"', false)->assertDontSee('rel="next"', false)
            ->assertSee('21 – 27 تشرين التاني');

        // A meal logged on Monday 2026-11-10, two weeks before this one (Saturday 21 to Friday 27).
        $this->travelTo(CarbonImmutable::parse('2026-11-10 13:00', 'Asia/Damascus'));
        $this->post(route('site.daily.store', $meal), ['amount' => 1, 'meal_type' => 'lunch']);
        $this->travelTo(CarbonImmutable::parse('2026-11-25 20:30', 'Asia/Damascus'));

        $this->get(route('site.daily'))->assertOk()
            ->assertSee(route('site.daily', ['date' => '2026-11-18']), false)
            ->assertDontSee('rel="next"', false);

        $this->get(route('site.daily', ['date' => '2026-11-18']))->assertOk()
            ->assertSee('14 – 20 تشرين التاني')
            ->assertSee(route('site.daily', ['date' => '2026-11-11']), false)
            // The week after is this one: it opens today itself.
            ->assertSee('rel="next"', false);

        // The week of the first meal: it shows, and there is nothing before it.
        $this->get(route('site.daily', ['date' => '2026-11-11']))->assertOk()
            ->assertSee('7 – 13 تشرين التاني')
            ->assertDontSee('rel="prev"', false)
            ->assertSee(route('site.daily', ['date' => '2026-11-10']), false);

        $this->get(route('site.daily', ['date' => '2026-11-10']))->assertOk()->assertSee('مجدرة');
    }

    public function test_without_a_profile_the_day_asks_to_complete_it(): void
    {
        $user = User::forceCreate(['name' => 'جديد', 'email' => 'n@example.com', 'password' => 'x-secret-1', 'is_active' => true, 'email_verified_at' => now()]);

        $this->actingAs($user)->get(route('site.daily'))->assertOk()
            ->assertSee('كمّل ملفك')
            ->assertDontSee('شو بيناسبك');
    }

    public function test_a_missing_dish_goes_to_the_admin_and_shows_on_the_day_without_values(): void
    {
        $admin = User::forceCreate(['name' => 'المدير', 'email' => 'admin@example.com', 'password' => 'x-secret-1', 'is_active' => true]);
        $admin->assignRole(Access::SUPER_ADMIN);
        $user = $this->person();

        $this->actingAs($user)->post(route('site.daily.request'), [
            'food_name' => 'شاورما  دجاج', 'ingredients' => 'خبز صاج، دجاج، توم، مخلل', 'meal_type' => 'dinner',
        ])->assertRedirect(route('site.daily'));

        $request = FoodRequest::sole();
        $this->assertSame('شاورما دجاج', $request->name);
        $this->assertSame(FoodRequestStatus::Pending, $request->status);
        $entry = MealLog::sole();
        $this->assertNull($entry->energy_kcal);
        $this->assertSame($request->id, $entry->food_request_id);

        $this->assertSame(1, $admin->notifications()->count());

        $this->get(route('site.daily'))->assertOk()
            ->assertSee('وصلنا طلبك')
            ->assertSee('طلبنا إضافتها، ما انحسبت لسا')
            // Nothing counted: all of the day is left.
            ->assertSee(number_format(round($this->need($user))));
    }

    public function test_a_request_needs_its_ingredients(): void
    {
        $user = $this->person();

        $this->actingAs($user)->from(route('site.daily'))->post(route('site.daily.request'), [
            'food_name' => 'شاورما', 'ingredients' => '', 'meal_type' => 'dinner',
        ])->assertSessionHasErrors('ingredients');

        $this->assertSame(0, FoodRequest::count());
        $this->assertSame(0, MealLog::count());
    }

    public function test_the_admin_links_every_request_for_one_dish_and_each_person_hears(): void
    {
        $first = $this->person();
        $second = $this->person('ليلى');
        $this->actingAs($first)->post(route('site.daily.request'), ['food_name' => 'كبّة مقلية', 'ingredients' => 'برغل ولحمة', 'meal_type' => 'lunch']);
        $this->actingAs($second)->post(route('site.daily.request'), ['food_name' => 'كبة مقلية', 'ingredients' => 'برغل، لحمة، صنوبر', 'meal_type' => 'lunch']);
        $this->actingAs($second)->post(route('site.daily.request'), ['food_name' => 'فلافل', 'ingredients' => 'حمص وكزبرة', 'meal_type' => 'lunch']);
        $meal = $this->approvedMeal('كبة مقلية');

        $this->signInAs();
        $component = Livewire::test(ListFoodRequests::class)
            ->assertCanSeeTableRecords(FoodRequest::all())
            ->assertSee('2 أشخاص');

        $component->callAction(TestAction::make('added')->table(FoodRequest::where('user_id', $first->id)->first()), ['product_id' => $meal->id])
            ->assertHasNoActionErrors();

        $this->assertSame(2, FoodRequest::where('status', FoodRequestStatus::Added)->where('product_id', $meal->id)->count());
        $this->assertSame(FoodRequestStatus::Pending, FoodRequest::where('name', 'فلافل')->value('status'));
        foreach ([$first, $second] as $person) {
            $notice = $person->notifications()->where('data->format', 'site')->sole();
            $this->assertStringContainsString('كبة مقلية', $notice->data['title']);
            $this->assertSame(route('site.meals.show', $meal->id, absolute: false), $notice->data['url']);
        }

        $component->callAction(TestAction::make('dismiss')->table(FoodRequest::where('name', 'فلافل')->first()), ['note' => 'قريباً'])
            ->assertHasNoActionErrors();
        $this->assertSame(FoodRequestStatus::Dismissed, FoodRequest::where('name', 'فلافل')->value('status'));
    }

    public function test_only_the_admin_sees_the_requested_foods(): void
    {
        $this->signInAs(Access::NUTRITIONIST);
        $this->get(ListFoodRequests::getUrl())->assertForbidden();
    }

    public function test_the_dishes_that_suit_this_hours_meal_fit_in_what_is_left(): void
    {
        $user = $this->person();
        $breakfast = ProductCategory::forceCreate(['brand_id' => $this->brand->id, 'name' => 'الفطور']);
        $dinner = ProductCategory::forceCreate(['brand_id' => $this->brand->id, 'name' => 'وجبات العشاء']);
        $this->approvedMeal('فول مدمس', ['category_id' => $breakfast->id]);
        $this->approvedMeal('متبل', ['category_id' => $dinner->id]);
        $this->approvedMeal('مجدرة بلا قسم');

        $this->actingAs($user)->get(route('site.daily'))->assertOk()
            ->assertSee('شو بيناسبك على العشا؟')
            ->assertSee('متبل')
            ->assertSee('مجدرة بلا قسم')
            ->assertDontSee('فول مدمس');

        $this->travelTo(CarbonImmutable::parse('2026-11-25 08:00', 'Asia/Damascus'));
        $this->get(route('site.daily'))->assertSee('شو بيناسبك على الفطور؟')
            ->assertSee('فول مدمس')->assertDontSee('متبل');
    }

    public function test_a_dish_bigger_than_what_is_left_is_not_suggested(): void
    {
        $user = $this->person();
        $this->approvedMeal('مجدرة');
        $big = $this->approvedMeal('صحن كبير');
        foreach (range(1, 7) as $i) {
            $this->actingAs($user)->post(route('site.daily.store', $big), ['amount' => 1, 'meal_type' => 'lunch']);
        }

        $this->get(route('site.daily'))->assertOk()
            ->assertSee('زدت اليوم')
            ->assertSee('ما لقينا وجبة على العشا');
    }

    public function test_the_day_reads_the_same_number_of_queries_whatever_was_eaten(): void
    {
        $user = $this->person();
        $meal = $this->approvedMeal('مجدرة');
        $this->actingAs($user)->post(route('site.daily.store', $meal), ['amount' => 1, 'meal_type' => 'lunch']);
        $this->get(route('site.daily'))->assertOk();

        $count = function () {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->get(route('site.daily'))->assertOk();
            $n = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $n;
        };

        $few = $count();
        foreach (range(1, 12) as $i) {
            $this->post(route('site.daily.store', $meal), ['amount' => 0.25, 'meal_type' => 'snack']);
        }
        $this->post(route('site.daily.request'), ['food_name' => 'شاورما', 'ingredients' => 'خبز ودجاج', 'meal_type' => 'dinner']);

        $this->assertSame($few, $count());
    }

    public function test_the_account_menu_holds_everything_about_me(): void
    {
        $user = $this->person();

        $this->actingAs($user)->get(route('home'))->assertOk()
            ->assertSee('aria-label="حسابي: سامر"', false)
            ->assertSee(route('site.daily'))
            ->assertSee(route('site.criteria'))
            ->assertSee(route('site.my-nutritionist'))
            ->assertSee('تسجيل الخروج')
            ->assertDontSee('معلومات الحساب');
    }

    public function test_the_hour_picks_the_meal(): void
    {
        $this->assertSame(MealType::Breakfast, MealType::forHour(6));
        $this->assertSame(MealType::Breakfast, MealType::forHour(10));
        $this->assertSame(MealType::Lunch, MealType::forHour(11));
        $this->assertSame(MealType::Lunch, MealType::forHour(16));
        $this->assertSame(MealType::Dinner, MealType::forHour(17));
        $this->assertSame(MealType::Dinner, MealType::forHour(23));
    }
}
