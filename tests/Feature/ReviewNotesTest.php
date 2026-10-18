<?php

namespace Tests\Feature;

use App\Auth\Access;
use App\Enums\FoodSourceStatus;
use App\Enums\NutritionApprovalStatus;
use App\Livewire\ReviewNotesPanel;
use App\Models\Brand;
use App\Models\DataSource;
use App\Models\Food;
use App\Models\FoodNutrientValue;
use App\Models\FoodSourceRecord;
use App\Models\MeasureUnit;
use App\Models\Nutrient;
use App\Models\Product;
use App\Models\ProductRecipe;
use App\Models\ProductRecipeIngredient;
use App\Models\ProductReviewNote;
use App\Models\User;
use App\Services\Nutrition\ProductReviewNotes;
use App\Services\Nutrition\RecipeAnalysisService;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

/** The note loop: nutritionist asks for a change, the brand owner fixes it, then approval. */
class ReviewNotesTest extends TestCase
{
    use RefreshDatabase;

    private Brand $brand;

    private Product $product;

    private User $owner;

    private User $otherOwner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        $this->brand = Brand::create(['name' => 'فلافل أبو محمد']);
        $other = Brand::create(['name' => 'شاورما المنافس']);
        $this->owner = $this->user('owner@example.test', Access::BRAND_OWNER);
        $this->owner->brands()->attach($this->brand->id);
        $this->otherOwner = $this->user('other@example.test', Access::BRAND_OWNER);
        $this->otherOwner->brands()->attach($other->id);

        DataSource::create(['code' => 'internal_product_recipes', 'name' => 'وصفات المنتجات', 'type' => 'internal_product_recipes']);
        $source = DataSource::create(['code' => 'usda_fdc_sr_legacy', 'name' => 'USDA SR Legacy', 'type' => 'usda_fdc']);
        $lentils = FoodSourceRecord::create([
            'food_id' => Food::create(['name_ar' => 'عدس'])->id, 'data_source_id' => $source->id,
            'external_ref' => '172420', 'status' => FoodSourceStatus::Active,
        ]);
        $energy = Nutrient::create(['code' => 'energy_kcal', 'name_ar' => 'الطاقة', 'name_en' => 'Energy', 'unit' => 'kcal', 'group' => 'energy', 'is_core' => true]);
        FoodNutrientValue::create(['food_source_record_id' => $lentils->id, 'nutrient_id' => $energy->id, 'amount_per_100g' => 352, 'unit' => 'kcal']);

        $this->product = Product::create(['brand_id' => $this->brand->id, 'name' => 'مجدرة']);
        $recipe = ProductRecipe::create(['product_id' => $this->product->id, 'servings' => 1, 'weight_after_cooking_g' => 250]);
        ProductRecipeIngredient::create([
            'product_recipe_id' => $recipe->id, 'food_source_record_id' => $lentils->id,
            'measure_unit_id' => MeasureUnit::create(['code' => 'g', 'name_ar' => 'غرام', 'dimension' => 'mass', 'base_factor' => 1])->id,
            'amount' => 200, 'sort_order' => 1,
        ]);
    }

    private function user(string $email, string $role): User
    {
        $user = User::forceCreate(['name' => $role, 'email' => $email, 'password' => 'secret-password1', 'is_active' => true]);
        $user->assignRole($role);

        return $user;
    }

    private function approvalStatus(): NutritionApprovalStatus
    {
        return NutritionApprovalStatus::of(NutritionApprovalStatus::withApprovalData(Product::query())->findOrFail($this->product->id));
    }

    public function test_a_note_reaches_the_brand_managers_and_blocks_approval_until_fixed(): void
    {
        $nutritionist = $this->signInAs(Access::NUTRITIONIST);

        Livewire::test(ReviewNotesPanel::class, ['productId' => $this->product->id])
            ->callAction('sendNote', ['body' => 'كمية العدس 200 غ لصحن واحد؟ تأكد من الوزن.'])
            ->assertHasNoFormErrors();

        $note = ProductReviewNote::sole();
        $this->assertTrue($note->isOpen());
        $this->assertSame(NutritionApprovalStatus::ChangesRequested, $this->approvalStatus());
        $this->assertSame(1, $this->owner->notifications()->count());
        $this->assertSame(0, $this->otherOwner->notifications()->count());
        $this->assertStringContainsString('مجدرة', $this->owner->notifications()->first()->data['title']);

        // No approval while the note is open, not even by calling the service directly.
        $service = new RecipeAnalysisService();
        try {
            $service->approve($this->product, $service->analyze($this->product), $nutritionist);
            $this->fail('approval must wait for the open note');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('ملاحظة مفتوحة', $e->getMessage());
        }
        $this->assertSame(0, FoodNutrientValue::where('product_id', $this->product->id)->count());

        // The owner fixes it and says what changed; the nutritionist hears back.
        $this->actingAs($this->owner);
        Livewire::test(ReviewNotesPanel::class, ['productId' => $this->product->id])
            ->callAction(TestAction::make('resolveNote')->arguments(['note' => $note->id]), ['reply' => 'صحّحت العدس إلى 150 غ.']);

        $note->refresh();
        $this->assertFalse($note->isOpen());
        $this->assertSame('صحّحت العدس إلى 150 غ.', $note->resolution);
        $this->assertSame($this->owner->id, (int) $note->resolved_by);
        $this->assertStringContainsString('150 غ', $nutritionist->notifications()->first()->data['body']);
        $this->assertSame(NutritionApprovalStatus::Awaiting, $this->approvalStatus());

        $service->approve($this->product, $service->analyze($this->product->fresh()), $nutritionist);
        $this->assertSame(1, FoodNutrientValue::where('product_id', $this->product->id)->count());
    }

    public function test_each_side_only_sees_its_own_buttons(): void
    {
        $note = app(ProductReviewNotes::class)->send($this->product, $this->user('n@example.test', Access::NUTRITIONIST), 'الوزن بعد الطبخ ناقص.');

        $this->actingAs($this->owner);
        Livewire::test(ReviewNotesPanel::class, ['productId' => $this->product->id])
            ->assertActionHidden('sendNote')
            ->assertActionHidden(TestAction::make('withdrawNote')->arguments(['note' => $note->id]))
            ->assertActionVisible(TestAction::make('resolveNote')->arguments(['note' => $note->id]))
            ->assertSee('الوزن بعد الطبخ ناقص.')
            // What the page draws, not only what the action says about itself.
            ->assertSee('تم التعديل')
            ->assertDontSee('إرسال ملاحظة لصاحب البراند')
            ->assertDontSee('إغلاق بلا تعديل');

        $this->signInAs(Access::NUTRITIONIST);
        Livewire::test(ReviewNotesPanel::class, ['productId' => $this->product->id])
            ->assertActionVisible('sendNote')
            ->assertActionHidden(TestAction::make('resolveNote')->arguments(['note' => $note->id]))
            ->assertSee('إرسال ملاحظة لصاحب البراند')
            ->assertSee('إغلاق بلا تعديل')
            ->assertDontSee('تم التعديل')
            ->callAction(TestAction::make('withdrawNote')->arguments(['note' => $note->id]));
        $this->assertFalse($note->fresh()->isOpen());
    }

    public function test_another_brand_owner_cannot_open_the_notes(): void
    {
        $this->actingAs($this->otherOwner);

        Livewire::test(ReviewNotesPanel::class, ['productId' => $this->product->id])->assertNotFound();
    }

    public function test_an_empty_note_is_refused(): void
    {
        $this->signInAs(Access::NUTRITIONIST);

        Livewire::test(ReviewNotesPanel::class, ['productId' => $this->product->id])
            ->callAction('sendNote', ['body' => ''])
            ->assertHasFormErrors(['body' => 'required']);
        $this->assertSame(0, ProductReviewNote::count());
    }
}
