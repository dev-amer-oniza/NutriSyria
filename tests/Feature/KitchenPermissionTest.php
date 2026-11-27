<?php

namespace Tests\Feature;

use App\Auth\Access;
use App\Enums\MealType;
use App\Filament\Clusters\Kitchen\Pages\KitchenSettings;
use App\Filament\Clusters\Kitchen\Resources\Dishes\KitchenDishResource;
use App\Filament\Clusters\Kitchen\Resources\FoodRequests\FoodRequestResource;
use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Role;
use App\Models\User;
use App\Services\Daily\FoodRequests;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** «إدارة المطبخ السوري»: the kitchen section, until now the admin's alone, for any role given it. */
class KitchenPermissionTest extends TestCase
{
    use RefreshDatabase;

    private function kitchenRole(): string
    {
        Role::create(['name' => 'custom-kitchen', 'label' => 'محرّر المطبخ', 'guard_name' => 'web'])
            ->givePermissionTo(Access::KITCHEN_MANAGE);

        return 'custom-kitchen';
    }

    public function test_a_role_with_the_permission_runs_the_whole_kitchen(): void
    {
        $dish = Product::create(['brand_id' => Brand::platform()->id, 'name' => 'مجدرة']);
        $category = new ProductCategory(['name' => 'غداء']);
        $category->brand_id = Brand::platform()->id;
        $category->save();
        $user = $this->signInAs($this->kitchenRole());

        $this->get(KitchenDishResource::getUrl('index'))->assertOk();
        $this->get(KitchenDishResource::getUrl('edit', ['record' => $dish]))->assertOk();
        $this->get(KitchenSettings::getUrl())->assertOk();
        $this->get(FoodRequestResource::getUrl('index'))->assertOk();
        $this->assertTrue($user->can('update', $dish));
        $this->assertTrue($user->can('approveNutrition', $dish));
        $this->assertTrue($user->can('update', $category));
    }

    public function test_it_opens_the_kitchen_only_not_the_restaurants(): void
    {
        $theirs = Product::create(['brand_id' => Brand::create(['name' => 'مطعم'])->id, 'name' => 'كرالا']);
        $user = $this->signInAs($this->kitchenRole());

        $this->assertFalse($user->can('view', $theirs));
        $this->assertFalse($user->can('approveNutrition', $theirs));
    }

    public function test_without_it_the_kitchen_stays_closed(): void
    {
        $dish = Product::create(['brand_id' => Brand::platform()->id, 'name' => 'مجدرة']);
        $reviewer = $this->signInAs(Access::REVIEWER);

        $this->get(KitchenDishResource::getUrl('index'))->assertForbidden();
        $this->assertFalse($reviewer->can('approveNutrition', $dish));
    }

    public function test_a_dish_asked_for_reaches_the_kitchen_manager_too(): void
    {
        $manager = User::forceCreate(['name' => 'محرّر', 'email' => 'k@example.test', 'password' => 'secret-password', 'is_active' => true]);
        $manager->assignRole($this->kitchenRole());
        $person = User::forceCreate(['name' => 'زائر', 'email' => 'p@example.test', 'password' => 'secret-password', 'is_active' => true]);

        app(FoodRequests::class)->request($person, 'فريكة', 'فريكة مع دجاج', MealType::Lunch);

        $this->assertSame(1, $manager->notifications()->count());
    }
}
