<?php

namespace Tests\Feature;

use App\Auth\Access;
use App\Enums\AuthCodePurpose;
use App\Filament\Clusters\Brands\Resources\Brands\Pages\EditBrand;
use App\Filament\Widgets\AccountsStats;
use App\Models\Brand;
use App\Models\DataSource;
use App\Models\FoodNutrientValue;
use App\Models\Product;
use App\Models\ProductRecipe;
use App\Models\ProductRecipeIngredient;
use App\Models\User;
use App\Notifications\AuthCodeNotification;
use App\Services\Auth\RoleRequestService;
use App\Services\Nutrition\RecipeAnalysisService;
use Filament\Facades\Filament;
use Filament\Notifications\DatabaseNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\Concerns\MealFixtures;
use Tests\TestCase;

/**
 * The bells added with the owner's choices (1a, plain sign-ups a, suggestions 1 2 3): role
 * requests and their answers, the accounts numbers, a product waiting for or getting approval,
 * and a brand owner linked to a restaurant. Notices are written at once (no queue).
 */
class NotificationsTest extends TestCase
{
    use MealFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->setUpMeals();
    }

    private function user(string $role, string $name = 'x'): User
    {
        $user = User::forceCreate(['name' => $name, 'email' => uniqid().'@example.com', 'password' => 'x-secret-1', 'is_active' => true, 'email_verified_at' => now()]);
        $user->assignRole($role);

        return $user;
    }

    /** Panel-bell notices of a user whose title contains $text. */
    private function panelNotices(User $user, string $text): int
    {
        return $user->notifications()->where('data->format', 'filament')->where('data->title', 'like', "%{$text}%")->count();
    }

    public function test_a_new_sign_up_asks_for_no_role_and_tells_no_admin(): void
    {
        Notification::fake();
        $admin = $this->user(Access::SUPER_ADMIN);

        $this->post('/register', ['name' => 'أبو عبدو', 'email' => 'abdo@example.com', 'password' => 'zaatar-2026'])
            ->assertRedirect('/verify-email');
        $applicant = User::where('email', 'abdo@example.com')->sole();
        $code = null;
        Notification::assertSentTo($applicant, AuthCodeNotification::class, function (AuthCodeNotification $n) use (&$code) {
            $code = $n->purpose === AuthCodePurpose::VerifyEmail ? $n->code : $code;

            return true;
        });
        $this->post('/verify-email', ['code' => $code]);

        Notification::assertNotSentTo($admin, DatabaseNotification::class);
        $this->assertNull($applicant->fresh()->requested_role);
    }

    public function test_the_person_hears_the_answer_in_the_site_bell(): void
    {
        $owner = $this->user(Access::NUTRITIONIST);
        $owner->syncRoles([]);
        $owner->forceFill(['requested_role' => Access::BRAND_OWNER, 'role_requested_at' => now()])->save();
        $nutritionist = User::forceCreate(['name' => 'ريم', 'email' => 'reem@example.com', 'password' => 'x-secret-1', 'is_active' => true]);
        $nutritionist->forceFill(['requested_role' => Access::NUTRITIONIST, 'role_requested_at' => now()])->save();

        app(RoleRequestService::class)->approve($owner);
        app(RoleRequestService::class)->decline($nutritionist);

        $yes = $owner->notifications()->where('data->format', 'site')->sole();
        $this->assertSame('صرت صاحب مطعم أو براند على NutriSyria', $yes->data['title']);
        $this->assertSame('/admin', $yes->data['url']);
        $this->assertSame('ما انقبل طلب حساب الأخصائي', $nutritionist->notifications()->where('data->format', 'site')->sole()->data['title']);
    }

    public function test_the_dashboard_counts_new_accounts_and_waiting_requests(): void
    {
        $this->user(Access::SUPER_ADMIN)->forceFill(['created_at' => now()->subDays(30)])->save();
        foreach (range(1, 3) as $i) {
            User::forceCreate(['name' => "n$i", 'email' => "n$i@example.com", 'password' => 'x-secret-1', 'is_active' => true]);
        }
        User::where('email', 'n1@example.com')->first()->forceFill(['requested_role' => Access::NUTRITIONIST])->save();
        User::where('email', 'n3@example.com')->first()->forceFill(['created_at' => now()->subDays(10)])->save();

        $this->actingAs(User::role(Access::SUPER_ADMIN)->first());
        Filament::setCurrentPanel('admin');

        Livewire::test(AccountsStats::class)
            ->assertSeeInOrder(['حسابات جديدة هالأسبوع', '2', 'طلبات أدوار تنتظر', '1']);
    }

    public function test_a_recipe_waiting_tells_the_nutritionists_once_and_approval_tells_the_restaurant(): void
    {
        $nutritionist = $this->user(Access::NUTRITIONIST, 'ريم');
        $manager = $this->user(Access::BRAND_OWNER, 'أبو عبدو');
        $this->brand->managers()->attach($manager->id);
        DataSource::create(['code' => 'internal_product_recipes', 'name' => 'recipes', 'type' => 'recipe', 'priority' => 0]);
        foreach (['energy_kcal' => 116, 'protein_g' => 9, 'fat_g' => 0.4, 'carbohydrate_g' => 20] as $code => $amount) {
            FoodNutrientValue::create(['food_source_record_id' => $this->lentils->id, 'nutrient_id' => $this->nutrients[$code]->id,
                'amount_per_100g' => $amount, 'unit' => $this->nutrients[$code]->unit]);
        }

        // The brand owner writes the recipe, line by line: one notice for the review.
        $product = Product::create(['brand_id' => $this->brand->id, 'name' => 'مجدرة']);
        $recipe = ProductRecipe::create(['product_id' => $product->id, 'servings' => 2, 'weight_after_cooking_g' => 500]);
        foreach ([300, 50] as $i => $grams) {
            ProductRecipeIngredient::create(['product_recipe_id' => $recipe->id, 'food_source_record_id' => $this->lentils->id,
                'measure_unit_id' => $this->grams->id, 'amount' => $grams, 'sort_order' => $i + 1]);
        }
        $this->assertSame(1, $this->panelNotices($nutritionist, '«مجدرة» من بيت المجدرة ينتظر اعتمادك'));
        $this->assertSame(0, $this->panelNotices($manager, 'ينتظر'));

        // Approved: the restaurant hears; a later change asks again.
        $service = app(RecipeAnalysisService::class);
        $service->approve($product->fresh(), $service->analyze($product->fresh()), $nutritionist);
        $this->assertSame(1, $this->panelNotices($manager, 'اعتُمدت قيم «مجدرة»'));
        $this->assertNull(DB::table('product_recipes')->where('id', $recipe->id)->value('review_requested_at'));

        $this->travel(1)->hours();
        ProductRecipeIngredient::where('product_recipe_id', $recipe->id)->first()->update(['amount' => 280]);
        $this->assertSame(2, $this->panelNotices($nutritionist, 'ينتظر اعتمادك'));
    }

    public function test_the_platform_kitchen_tells_nobody(): void
    {
        $nutritionist = $this->user(Access::NUTRITIONIST);
        $product = Product::create(['brand_id' => Brand::platform()->id, 'name' => 'فتة']);
        $recipe = ProductRecipe::create(['product_id' => $product->id, 'servings' => 2, 'weight_after_cooking_g' => 500]);
        ProductRecipeIngredient::create(['product_recipe_id' => $recipe->id, 'food_source_record_id' => $this->lentils->id,
            'measure_unit_id' => $this->grams->id, 'amount' => 300, 'sort_order' => 1]);

        $this->assertSame(0, $nutritionist->notifications()->count());
    }

    public function test_a_brand_owner_hears_when_linked_to_a_restaurant(): void
    {
        $owner = $this->user(Access::BRAND_OWNER, 'أبو عبدو');
        $this->actingAs($this->user(Access::SUPER_ADMIN));
        Filament::setCurrentPanel('admin');

        Livewire::test(EditBrand::class, ['record' => $this->brand->id])
            ->fillForm(['owner_id' => $owner->id])
            ->call('save')
            ->assertHasNoFormErrors();
        // Saved again with the same owner: no second notice.
        Livewire::test(EditBrand::class, ['record' => $this->brand->id])->call('save');

        $this->assertSame(1, $this->panelNotices($owner, 'صرت المسؤول عن المطعم «بيت المجدرة»'));
    }
}
