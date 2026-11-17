<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MealFixtures;
use Tests\TestCase;

/**
 * The meals pages and the personal verdict. The person is the design's example man
 * (2240 kcal, balanced): one meal = 672 kcal. The mujaddara serving (250 g) is 415 kcal.
 */
class MealPagesTest extends TestCase
{
    use MealFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->setUpMeals();
    }

    private function person(array $profile = []): User
    {
        $user = User::forceCreate(['name' => 'سامر', 'email' => uniqid().'@example.com', 'password' => 'x-secret-1', 'is_active' => true, 'email_verified_at' => now()]);
        $user->profile()->create($profile + [
            'sex' => 'male', 'birth_year' => now()->year - 30, 'height_cm' => 178, 'weight_kg' => 80,
            'activity_level' => 'light', 'goal' => 'lose', 'health_conditions' => [], 'allergies' => [],
            'completed_at' => now(),
        ]);

        return $user;
    }

    public function test_a_guest_sees_the_meals_and_is_invited_to_sign_in(): void
    {
        $meal = $this->approvedMeal('مجدرة');

        $this->get(route('site.meals'))->assertOk()
            ->assertSee('مجدرة')
            ->assertSee('سجّل الدخول لترى مدى مناسبة كل وجبة')
            ->assertSee(route('site.meals.show', $meal), false);

        $this->get(route('site.meals.show', $meal))->assertOk()
            ->assertSee('القيم لحصة')
            ->assertSee('415')
            ->assertSee('عدس')
            ->assertSee('أنشئ حسابك');
    }

    public function test_every_meal_names_its_restaurant_with_a_link_to_its_page(): void
    {
        $meal = $this->approvedMeal('مجدرة');
        $restaurant = route('site.brands.show', $this->brand);
        $link = 'href="'.$restaurant.'">بيت المجدرة</a>';

        $this->get(route('site.meals'))->assertOk()
            ->assertSee($link, false)
            ->assertSee('href="'.route('site.meals.show', $meal).'">مجدرة</a>', false);   // the card still opens the meal
        $this->get('/')->assertSee($link, false);
        $this->get(route('site.meals.show', $meal))->assertSee($link, false);

        $this->actingAs($this->person());
        $this->get('/')->assertSee($link, false);                                         // the fit panel too

        // On the restaurant's own page the cards don't repeat its name.
        $html = $this->get($restaurant)->assertOk()->getContent();
        $this->assertStringNotContainsString('ns-dish__brand', $html);
    }

    public function test_an_unapproved_or_hidden_meal_is_not_found(): void
    {
        $hidden = $this->approvedMeal('موقوفة', ['is_active' => false]);
        $draft = Product::create(['brand_id' => $this->brand->id, 'name' => 'مسودة']);

        $this->get(route('site.meals.show', $hidden))->assertNotFound();
        $this->get(route('site.meals.show', $draft))->assertNotFound();
        $this->get(route('site.meals'))->assertDontSee('موقوفة')->assertDontSee('مسودة');
    }

    public function test_search_finds_a_meal_or_its_restaurant_whatever_the_spelling(): void
    {
        $this->approvedMeal('مجدرة');
        $this->approvedMeal('فتة باذنجان')->update(['brand_id' => Brand::create(['name' => 'مطعم الشام'])->id]);

        $this->get(route('site.meals', ['q' => 'مجدره']))->assertSee('مجدرة')->assertDontSee('فتة باذنجان');
        $this->get(route('site.meals', ['q' => 'الشآم']))->assertSee('فتة باذنجان')->assertDontSee('>مجدرة<', false);
        $this->get(route('site.meals', ['q' => 'شاورما']))->assertSee('لا وجبات تطابق');
    }

    public function test_a_signed_in_person_gets_a_verdict_reasons_and_a_portion(): void
    {
        $meal = $this->approvedMeal('مجدرة');
        $this->actingAs($this->person());

        $this->get(route('site.meals.show', $meal))->assertOk()
            ->assertSee('مدى مناسبتها لك')
            ->assertSee('مناسبة جداً')
            ->assertSee('19% من احتياجك اليومي')            // 415 / 2240
            ->assertSee('الحصة كاملة')
            ->assertSee('لا تتوفر قيمة الدهون المشبعة')      // no saturated fat value: named, not guessed
            ->assertSee('حسب قالبك: المتوازن');
    }

    public function test_cards_show_the_verdict_and_the_filter_keeps_what_suits(): void
    {
        $this->approvedMeal('مجدرة');
        $this->approvedMeal('منسف كبير', per100g: ['energy_kcal' => 500, 'protein_g' => 12, 'carbohydrate_g' => 30, 'fat_g' => 25]);
        $this->actingAs($this->person());

        $this->get(route('site.meals'))->assertOk()
            ->assertSee('ns-verdict--sm', false)
            ->assertSee('تحتاج انتباهاً')                   // 1250 kcal: 86% over 672, a target (step 2)
            ->assertSee('المناسبة لي');

        $this->get(route('site.meals', ['suits' => 1]))->assertSee('مجدرة')->assertDontSee('منسف كبير');
    }

    public function test_a_rule_not_verified_yet_is_information_until_a_doctor_gives_the_number(): void
    {
        // 250 g with 17.5 g fat: within the day's verified fat share (22 g), past the
        // fat-intolerance meal limit (15 g) that is waiting for its references.
        $meal = $this->approvedMeal('مجدرة', per100g: ['energy_kcal' => 166, 'protein_g' => 6, 'carbohydrate_g' => 24.8, 'fat_g' => 7, 'fiber_g' => 3]);
        $this->actingAs($this->person(['health_conditions' => ['fat_intolerance']]));

        $this->get(route('site.meals.show', $meal))->assertOk()
            ->assertSee('مناسبة جداً')
            ->assertSee('على احتياجك العام')
            ->assertSee('للمعلومة، لا يدخل في التقييم بعد: دهون الوجبة فيها 18 غ، وحد «عدم تحمّل الدسم» 15 غ للوجبة قيد التوثيق.')
            ->assertSee('قيّمناها على: احتياجك العام (موثّق). قيد التوثيق: حدود عدم تحمّل الدسم. ننصحك بأخصائي.')
            // "من أين هذا الحد؟" opens the person's condition on the sources page (step 3b, 4a).
            ->assertSee(route('site.sources').'#fat_intolerance', false)
            ->assertSee('من أين هذا الحد؟');

        // The person's doctor gave them 15 g a meal: it judges now, as a strict limit.
        $this->put(route('site.criteria.update', 'meal_fat_g'), ['value' => 15, 'from_doctor' => '1'])->assertSessionHas('status');
        $this->get(route('site.criteria'))->assertSee('من طبيبك');
        $this->get(route('site.meals.show', $meal))->assertOk()
            ->assertSee('مناسبة باعتدال')
            ->assertSee('دهون الوجبة فيها 18 غ، والحد الذي حدّده لك طبيبك 15 غ للوجبة، أي أكثر بـ17%.')
            ->assertSee('وأرقام طبيبك')
            ->assertDontSee('للمعلومة، لا يدخل في التقييم بعد');

        // Back to the calculated value: information again.
        $this->delete(route('site.criteria.reset', 'meal_fat_g'))->assertSessionHas('status');
        $this->get(route('site.meals.show', $meal))->assertSee('مناسبة جداً')->assertSee('للمعلومة، لا يدخل في التقييم بعد');
    }

    public function test_an_allergen_in_an_ingredient_makes_the_meal_unfit(): void
    {
        $bulgur = $this->ingredient('برغل', allergens: ['gluten']);
        $meal = $this->approvedMeal('مجدرة', ingredient: [$this->lentils, $bulgur]);
        $this->actingAs($this->person(['health_conditions' => ['celiac']]));

        $this->get(route('site.meals.show', $meal))->assertOk()
            ->assertSee('يحتوي على: الغلوتين')
            ->assertSee('غير مناسبة لك الآن')
            ->assertSee('فيها الغلوتين')
            ->assertSee('لا ننصح بها لك الآن');
    }

    public function test_unchecked_ingredients_warn_a_person_with_allergies(): void
    {
        $meal = $this->approvedMeal('مجدرة'); // lentils: never checked
        $this->actingAs($this->person(['allergies' => ['sesame']]));

        $this->get(route('site.meals.show', $meal))
            ->assertSee('لم نتحقق بعد من مسبّبات الحساسية في كل مكوّناتها، فتأكد منها مع المطعم.');
    }

    public function test_an_incomplete_profile_is_asked_to_finish_it(): void
    {
        $meal = $this->approvedMeal('مجدرة');
        $user = User::forceCreate(['name' => 'جديد', 'email' => 'new@example.com', 'password' => 'x-secret-1', 'is_active' => true, 'email_verified_at' => now()]);

        $this->actingAs($user)->get(route('site.meals.show', $meal))->assertOk()->assertSee('أكمل ملفك');
        $this->get(route('site.meals'))->assertSee('أكمل ملفك لترى مدى مناسبة كل وجبة لك');
    }

    public function test_the_home_panel_judges_the_newest_meal_for_a_signed_in_person(): void
    {
        $meal = $this->approvedMeal('مجدرة');

        $this->get('/')->assertSee('مثال توضيحي');

        $this->actingAs($this->person());
        $this->get('/')->assertOk()
            ->assertSee('مدى مناسبتها لك، من معاييرك أنت')
            ->assertSee(route('site.meals.show', $meal), false)
            ->assertDontSee('مثال توضيحي لطريقة العرض');
    }

    public function test_the_meal_page_lists_every_known_value_in_its_groups(): void
    {
        $meal = $this->approvedMeal('فول مدمس', per100g: self::PER_100G + [
            'fat_sat_g' => 0.9, 'fat_mono_g' => 4.2, 'fat_poly_g' => 0.8, 'potassium_mg' => 245, 'vitamin_c_mg' => 7.3,
        ]);

        $this->get(route('site.meals.show', $meal))->assertOk()
            // The four main figures stay as they were.
            ->assertSee('سعرة حرارية')->assertSee('بروتين')
            ->assertSeeInOrder(['الدهون', 'المشبعة', 'الأحادية غير المشبعة', 'المتعددة غير المشبعة', 'المعادن', 'البوتاسيوم'])
            ->assertSee('10.5') // 4.2 g per 100 g, 250 g a serving
            // The values always shown keep their dash when unknown; the others are left out.
            ->assertSee('السكريات الكلية')->assertSee('الصوديوم')
            ->assertDontSee('الكوليسترول')->assertDontSee('المغنيسيوم')
            // Vitamins are folded, and only the known ones are listed.
            ->assertSee('<details class="ns-meal__group">', false)->assertSee('عرض الفيتامينات')
            ->assertSee('فيتامين C')->assertDontSee('فيتامين D');
    }

    public function test_a_meal_with_no_vitamin_has_no_vitamins_group(): void
    {
        $meal = $this->approvedMeal('مجدرة');

        $this->get(route('site.meals.show', $meal))->assertOk()
            ->assertSee('الدهون')->assertDontSee('عرض الفيتامينات');
    }
}
