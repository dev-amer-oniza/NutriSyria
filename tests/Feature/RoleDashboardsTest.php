<?php

namespace Tests\Feature;

use App\Auth\Access;
use App\Enums\FoodSourceStatus;
use App\Enums\NutritionApprovalStatus;
use App\Filament\Clusters\Brands\Resources\Products\ProductResource;
use App\Filament\Clusters\ImportReview\Resources\ImportBatches\Pages\EditImportBatch;
use App\Filament\Clusters\ImportReview\Resources\ImportBatches\RelationManagers\FoodSourceRecordsRelationManager;
use App\Filament\Widgets\FoodDatabaseStats;
use App\Filament\Widgets\ProductsAwaitingApproval;
use App\Filament\Widgets\ProductsWorkStats;
use App\Models\Brand;
use App\Models\DataSource;
use App\Models\Food;
use App\Models\FoodNutrientValue;
use App\Models\FoodSourceRecord;
use App\Models\ImportBatch;
use App\Models\MeasureUnit;
use App\Models\Nutrient;
use App\Models\Product;
use App\Models\ProductRecipe;
use App\Models\ProductRecipeIngredient;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RoleDashboardsTest extends TestCase
{
    use RefreshDatabase;

    private Brand $mine;

    private Brand $theirs;

    private FoodSourceRecord $lentils;

    private MeasureUnit $gram;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        $this->mine = Brand::create(['name' => 'فلافل أبو محمد']);
        $this->theirs = Brand::create(['name' => 'شاورما المنافس']);
        $source = DataSource::create(['code' => 'usda_fdc_sr_legacy', 'name' => 'USDA SR Legacy', 'type' => 'usda_fdc']);
        $this->lentils = FoodSourceRecord::create([
            'food_id' => Food::create(['name_ar' => 'عدس'])->id, 'data_source_id' => $source->id,
            'external_ref' => '172420', 'status' => FoodSourceStatus::Active,
        ]);
        $this->gram = MeasureUnit::create(['code' => 'g', 'name_ar' => 'غرام', 'dimension' => 'mass', 'base_factor' => 1]);
    }

    /** A product with one recipe line; $approved also gives it official values approved now. */
    private function product(Brand $brand, string $name, bool $withRecipe = true, bool $approved = false): Product
    {
        $product = Product::create(['brand_id' => $brand->id, 'name' => $name]);
        $recipe = ProductRecipe::create(['product_id' => $product->id, 'servings' => 1]);
        if ($withRecipe) {
            ProductRecipeIngredient::create([
                'product_recipe_id' => $recipe->id, 'food_source_record_id' => $this->lentils->id,
                'measure_unit_id' => $this->gram->id, 'amount' => 100, 'sort_order' => 1,
            ]);
        }
        if ($approved) {
            $energy = Nutrient::firstOrCreate(['code' => 'energy_kcal'], ['name_ar' => 'الطاقة', 'name_en' => 'Energy', 'unit' => 'kcal', 'group' => 'energy']);
            FoodNutrientValue::create(['product_id' => $product->id, 'nutrient_id' => $energy->id, 'amount_per_100g' => 352, 'unit' => 'kcal']);
            $recipe->forceFill(['nutrition_approved_at' => now()->addSecond()])->save();
        }

        return $product;
    }

    private function approvalOf(Product $product): NutritionApprovalStatus
    {
        return NutritionApprovalStatus::of(NutritionApprovalStatus::withApprovalData(Product::query())->findOrFail($product->id));
    }

    public function test_the_approval_status_follows_the_recipe(): void
    {
        $this->assertSame(NutritionApprovalStatus::NoRecipe, $this->approvalOf($this->product($this->mine, 'بلا وصفة', withRecipe: false)));
        $this->assertSame(NutritionApprovalStatus::Awaiting, $this->approvalOf($this->product($this->mine, 'جديد')));

        $approved = $this->product($this->mine, 'معتمد', approved: true);
        $this->assertSame(NutritionApprovalStatus::Approved, $this->approvalOf($approved));

        // An ingredient edited after the approval: the official values may be out of date.
        $this->travel(5)->minutes();
        $approved->recipe->ingredients()->first()->update(['amount' => 150]);
        $this->assertSame(NutritionApprovalStatus::Changed, $this->approvalOf($approved));
    }

    public function test_the_waiting_list_holds_awaiting_and_changed_products_of_the_users_brands_only(): void
    {
        $this->signInAs(Access::BRAND_OWNER)->brands()->attach($this->mine->id);
        $waiting = $this->product($this->mine, 'ينتظر');
        $done = $this->product($this->mine, 'معتمد', approved: true);
        $other = $this->product($this->theirs, 'منتج المنافس');
        $changed = $this->product($this->mine, 'تغيّر', approved: true);
        $this->travel(5)->minutes();
        $changed->recipe->ingredients()->first()->update(['amount' => 150]);

        Livewire::test(ProductsAwaitingApproval::class)
            ->assertCanSeeTableRecords([$waiting, $changed])
            ->assertCanNotSeeTableRecords([$done, $other]);
    }

    public function test_a_brand_owner_home_shows_their_products_not_the_database(): void
    {
        $this->signInAs(Access::BRAND_OWNER)->brands()->attach($this->mine->id);
        $this->product($this->mine, 'سندويشة فلافل');

        $this->get(Filament::getPanel('admin')->getUrl())->assertOk();
        $this->assertTrue(ProductsWorkStats::canView());
        $this->assertFalse(FoodDatabaseStats::canView());
        Livewire::test(ProductsWorkStats::class)->assertSee('منتجاتي')->assertDontSee('سجلات أغذية تنتظر المراجعة');
    }

    public function test_the_nutritionist_reads_a_product_and_its_records_without_edit_rights(): void
    {
        $this->signInAs(Access::NUTRITIONIST);
        $product = $this->product($this->theirs, 'سندويشة شاورما');

        $this->get(ProductResource::getUrl('view', ['record' => $product]))->assertOk()->assertSee('تحليل الوصفة');
        $this->get(ProductResource::getUrl('edit', ['record' => $product]))->assertForbidden();
        Livewire::test(ProductsWorkStats::class)->assertSee('بانتظار مراجعتك')->assertSee('سجلات أغذية تنتظر المراجعة');

        // Record details open in a side panel on the batch page, with the values.
        $batch = ImportBatch::create(['original_filename' => 'b.zip', 'file_path' => 'x', 'file_sha256' => str_repeat('a', 64), 'status' => 'committed']);
        $energy = Nutrient::create(['code' => 'energy_kcal', 'name_ar' => 'الطاقة', 'name_en' => 'Energy', 'unit' => 'kcal', 'group' => 'energy']);
        $record = FoodSourceRecord::create([
            'food_id' => Food::create(['name_ar' => 'برغل'])->id, 'data_source_id' => $this->lentils->data_source_id,
            'external_ref' => '170285', 'status' => FoodSourceStatus::PendingReview, 'import_batch_id' => $batch->id,
        ]);
        FoodNutrientValue::create(['food_source_record_id' => $record->id, 'nutrient_id' => $energy->id, 'amount_per_100g' => 342, 'unit' => 'kcal']);

        Livewire::test(FoodSourceRecordsRelationManager::class, ['ownerRecord' => $batch, 'pageClass' => EditImportBatch::class])
            ->assertActionVisible(TestAction::make('viewFullDetails')->table($record))
            // Opening the record's edit page needs food-database rights.
            ->assertActionHidden(TestAction::make('openRecord')->table($record))
            ->mountAction(TestAction::make('viewFullDetails')->table($record))
            ->assertActionMounted(TestAction::make('viewFullDetails')->table($record));

        $details = view('filament.clusters.import-review.components.record-details', ['record' => $record])->render();
        $this->assertStringContainsString('الطاقة', $details);
        $this->assertStringContainsString('342', $details);
        $this->assertStringContainsString('170285', $details);
    }
}
