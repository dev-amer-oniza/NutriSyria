<?php

namespace Tests\Feature;

use App\Models\Brand;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MealFixtures;
use Tests\TestCase;

/** "For restaurants" (the owner's decisions 1a 2a 3b) and its sign-up link. */
class PartnersPageTest extends TestCase
{
    use MealFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->setUpMeals();
    }

    public function test_the_page_explains_how_to_join_and_keeps_recipes_private(): void
    {
        $this->get(route('site.partners'))->assertOk()
            ->assertSee('قيم غذائية موثّقة لوجباتك، يراجعها مراجع الوصفات')
            ->assertSee('href="'.route('site.register').'"', false)
            ->assertSee('لنفعّله كمطعم أو براند')
            ->assertSeeInOrder(['أنشئ حسابك', 'أضف وصفاتك', 'يراجعها المراجع', 'تظهر وجباتك'])
            ->assertSee('الكميات لا تظهر على الموقع أبداً')
            ->assertSee('هل تظهر وصفتي للناس؟')
            ->assertDontSee('مجاني')                    // no price until the owner decides (3b)
            ->assertDontSee('قريباً');
    }

    public function test_live_numbers_show_only_from_five_restaurants(): void
    {
        $this->approvedMeal('مجدرة');

        $this->get(route('site.partners'))->assertDontSee('وجبة بقيم معتمدة')
            ->assertSee(route('site.brands.show', $this->brand), false); // a real restaurant page to look at

        foreach (range(1, 4) as $i) {
            Brand::create(['name' => "مطعم {$i}"]);
        }

        $this->get(route('site.partners'))->assertSee('مطعماً')->assertSee('وجبة بقيم معتمدة');
    }
}
