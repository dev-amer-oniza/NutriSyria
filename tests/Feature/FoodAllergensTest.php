<?php

namespace Tests\Feature;

use App\Auth\Access;
use App\Filament\Clusters\FoodDatabase\Resources\Food\Pages\EditFood;
use App\Filament\Clusters\FoodDatabase\Resources\Food\Pages\ListFood;
use App\Models\Food;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Marking a food's allergens in the panel (judged by MealFitCalculator on the site). */
class FoodAllergensTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_food_is_marked_with_its_allergens(): void
    {
        $this->signInAs(Access::SUPER_ADMIN);
        $bulgur = Food::create(['name_ar' => 'برغل']);
        $this->assertFalse($bulgur->fresh()->allergens_checked);

        Livewire::test(EditFood::class, ['record' => $bulgur->id])
            ->fillForm(['allergens' => ['gluten'], 'allergens_checked' => true])
            ->call('save')
            ->assertHasNoFormErrors();

        $bulgur->refresh();
        $this->assertSame(['gluten'], $bulgur->allergens);
        $this->assertTrue($bulgur->allergens_checked);
    }

    public function test_many_foods_are_marked_free_of_allergens_at_once(): void
    {
        $this->signInAs(Access::SUPER_ADMIN);
        $lentils = Food::create(['name_ar' => 'عدس']);
        $rice = Food::create(['name_ar' => 'أرز']);

        Livewire::test(ListFood::class)
            ->filterTable('allergens_checked', false)
            ->assertCanSeeTableRecords([$lentils, $rice])
            ->selectTableRecords([$lentils, $rice])
            ->callAction(TestAction::make('noAllergens')->table()->bulk())
            ->assertHasNoActionErrors();

        $this->assertTrue($lentils->fresh()->allergens_checked);
        $this->assertSame([], $rice->fresh()->allergens);
    }
}
