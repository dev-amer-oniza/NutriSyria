<?php

namespace Tests\Feature;

use App\Models\MealLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\KitchenRecipeFixtures;
use Tests\TestCase;

/**
 * "أكلتها" under "نتيجة طبختك" (the owner's decision a): the person's own recipe is logged with
 * values worked out again on the server from approved ingredients, a plate being one of the
 * servings they wrote. Ours: 280 g lentils (116 kcal/100 g) and 20 g oil on top (884), 2 servings.
 */
class CustomEatenTest extends TestCase
{
    use KitchenRecipeFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->setUpKitchenRecipe();
    }

    private function person(): User
    {
        return User::forceCreate(['name' => 'سامر', 'email' => uniqid().'@example.com', 'password' => 'x-secret-1', 'is_active' => true, 'email_verified_at' => now()]);
    }

    /** Their recipe: half the oil. 280 × 1.16 + 10 × 8.84 = 413.2 kcal, 206.6 a plate of 2. */
    private function mine(array $extra = []): array
    {
        return [
            'lines' => [
                ['record_id' => $this->lentils->id, 'unit_id' => $this->g->id, 'amount' => 280, 'after' => false],
                ['record_id' => $this->oil->id, 'unit_id' => $this->g->id, 'amount' => 10, 'after' => true],
            ],
            'servings' => 2,
            'weight_after' => 230,
        ] + $extra;
    }

    public function test_the_result_offers_ate_it_to_a_signed_in_person_only(): void
    {
        $guest = $this->postJson(route('site.kitchen.recipe.analyze', $this->dish->id), $this->mine())->assertOk()->json('html');
        $this->assertStringNotContainsString('قديش أكلت من طبختك؟', $guest);

        $html = $this->actingAs($this->person())
            ->postJson(route('site.kitchen.recipe.analyze', $this->dish->id), $this->mine())->assertOk()->json('html');
        $this->assertStringContainsString('أكلتها', $html);
        $this->assertStringContainsString('قديش أكلت من طبختك؟', $html);
        $this->assertStringContainsString(route('site.daily.custom', $this->dish->id), $html);
        $this->assertStringContainsString('name="eaten"', $html);
    }

    public function test_their_own_plate_is_logged_with_values_worked_out_on_the_server(): void
    {
        $user = $this->person();

        $this->actingAs($user)
            // A number sent with the recipe is never read: only the recipe is.
            ->postJson(route('site.daily.custom', $this->dish->id), $this->mine(['eaten' => 0.5, 'meal_type' => 'dinner', 'energy_kcal' => 1]))
            ->assertOk()
            ->assertJson(['redirect' => route('site.daily')]);

        $entry = MealLog::sole();
        $this->assertSame('مجدرة (طبختك)', $entry->name);
        $this->assertSame($this->dish->id, $entry->product_id);
        $this->assertEqualsWithDelta(103.3, $entry->energy_kcal, 0.1);   // half of 206.6
        $this->assertSame('dinner', $entry->meal_type->value);

        $this->get(route('site.daily'))->assertOk()->assertSee('مجدرة (طبختك)')->assertSee('نص الصحن');
    }

    public function test_an_unapproved_ingredient_an_odd_plate_or_a_guest_logs_nothing(): void
    {
        $pending = $this->record('سمنة', ['fat_g' => 99], status: 'pending_review');
        $body = $this->mine(['eaten' => 1, 'meal_type' => 'lunch']);
        $body['lines'][] = ['record_id' => $pending->id, 'unit_id' => $this->g->id, 'amount' => 10, 'after' => false];

        $this->postJson(route('site.daily.custom', $this->dish->id), $this->mine(['eaten' => 1, 'meal_type' => 'lunch']))->assertUnauthorized();

        $this->actingAs($this->person());
        $this->postJson(route('site.daily.custom', $this->dish->id), $body)->assertStatus(422);
        $this->postJson(route('site.daily.custom', $this->dish->id), $this->mine(['eaten' => 0.3, 'meal_type' => 'lunch']))->assertStatus(422);
        $this->postJson(route('site.daily.custom', $this->dish->id), $this->mine(['meal_type' => 'lunch']))->assertStatus(422);

        $this->assertSame(0, MealLog::count());
    }
}
