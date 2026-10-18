<?php

namespace Tests\Feature;

use App\Auth\Access;
use App\Enums\FoodSourceStatus;
use App\Filament\Clusters\Brands\Pages\RecipeAnalysis;
use App\Filament\Clusters\Brands\Resources\Brands\BrandResource;
use App\Filament\Clusters\Brands\Resources\Brands\Pages\ListBrands;
use App\Filament\Clusters\Brands\Resources\Products\Pages\CreateProduct;
use App\Filament\Clusters\Brands\Resources\Products\Pages\EditProduct;
use App\Filament\Clusters\Brands\Resources\Products\Pages\ListProducts;
use App\Filament\Clusters\Brands\Resources\Products\ProductResource;
use App\Filament\Clusters\Brands\Resources\Products\RelationManagers\RecipeIngredientsRelationManager;
use App\Filament\Clusters\FoodDatabase\Pages\CompareFoodSources;
use App\Filament\Clusters\FoodDatabase\Resources\Food\FoodResource;
use App\Filament\Clusters\ImportReview\Pages\SourceMatchingTools;
use App\Filament\Clusters\ImportReview\Pages\UploadImportBatch;
use App\Filament\Clusters\ImportReview\Resources\ImportBatches\ImportBatchResource;
use App\Filament\Clusters\ImportReview\Resources\ImportBatches\Pages\EditImportBatch;
use App\Filament\Clusters\ImportReview\Resources\ImportBatches\RelationManagers\FoodSourceRecordsRelationManager;
use App\Filament\Widgets\FoodDatabaseStats;
use App\Models\Brand;
use App\Models\DataSource;
use App\Models\Food;
use App\Models\FoodSourceRecord;
use App\Models\ImportBatch;
use App\Models\MeasureUnit;
use App\Models\Product;
use App\Models\ProductRecipe;
use App\Models\User;
use App\Policies\UserPolicy;
use Filament\Actions\CreateAction;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Tries to get past the access rules from each role's seat: other brands by URL, forged form
 * values, actions without the permission, pages outside the role. Each case must be refused.
 */
class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    private Brand $mine;

    private Brand $theirs;

    private Product $myProduct;

    private Product $theirProduct;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        $this->mine = Brand::create(['name' => 'فلافل أبو محمد']);
        $this->theirs = Brand::create(['name' => 'شاورما المنافس']);
        $this->myProduct = Product::create(['brand_id' => $this->mine->id, 'name' => 'سندويشة فلافل']);
        $this->theirProduct = Product::create(['brand_id' => $this->theirs->id, 'name' => 'سندويشة شاورما']);
        foreach ([$this->myProduct, $this->theirProduct] as $product) {
            ProductRecipe::create(['product_id' => $product->id, 'servings' => 1]);
        }
    }

    private function brandOwner(): User
    {
        $owner = $this->signInAs(Access::BRAND_OWNER);
        $owner->brands()->attach($this->mine->id);

        return $owner;
    }

    private function pendingRecord(): FoodSourceRecord
    {
        $source = DataSource::firstOrCreate(['code' => 'usda_fdc_sr_legacy'], ['name' => 'USDA SR Legacy', 'type' => 'usda_fdc']);
        $batch = ImportBatch::create(['original_filename' => 'b.zip', 'file_path' => 'x', 'file_sha256' => str_repeat('a', 64), 'status' => 'committed']);

        return FoodSourceRecord::create([
            'food_id' => Food::create(['name_ar' => 'عدس', 'is_active' => false])->id, 'data_source_id' => $source->id,
            'external_ref' => (string) random_int(1, 1e9), 'status' => FoodSourceStatus::PendingReview, 'import_batch_id' => $batch->id,
        ]);
    }

    // ------------------------------------------------------------------ getting in

    public function test_only_active_users_with_a_panel_role_get_in(): void
    {
        $panel = Filament::getPanel('admin');
        $noRole = User::forceCreate(['name' => 'x', 'email' => 'x@example.test', 'password' => 'secret-password']);
        $inactive = User::forceCreate(['name' => 'y', 'email' => 'y@example.test', 'password' => 'secret-password', 'is_active' => false]);
        $inactive->assignRole(Access::SUPER_ADMIN);
        $owner = User::forceCreate(['name' => 'z', 'email' => 'z@example.test', 'password' => 'secret-password']);
        $owner->assignRole(Access::BRAND_OWNER);

        $this->assertFalse($noRole->canAccessPanel($panel));
        $this->assertFalse($inactive->canAccessPanel($panel));
        $this->assertTrue($owner->canAccessPanel($panel));
    }

    public function test_the_old_roles_are_gone_and_the_password_reset_hole_is_closed(): void
    {
        $this->assertSame(
            [Access::BRAND_OWNER, Access::NUTRITIONIST, Access::SUPER_ADMIN],
            \App\Models\Role::query()->orderBy('name')->pluck('name')->all(),
        );
        $this->postJson('/api/set-password', ['email' => 'a@b.c', 'new_password' => 'x'])->assertNotFound();
    }

    public function test_every_model_in_the_panel_has_a_policy(): void
    {
        $this->signInAs();
        $models = [];
        foreach (Filament::getPanel('admin')->getResources() as $resource) {
            $models[] = $resource::getModel();
            foreach ($resource::getRelations() as $relationManager) {
                $relation = $relationManager::getRelationshipName();
                $models[] = get_class((new ($resource::getModel())())->{$relation}()->getRelated());
            }
        }

        foreach (array_filter(array_unique($models)) as $model) {
            $this->assertNotNull(Gate::getPolicyFor($model), "{$model} has no policy");
        }
    }

    public function test_the_super_admin_still_opens_every_page(): void
    {
        $this->signInAs();
        $food = Food::create(['name_ar' => 'حمص']);
        $batch = $this->pendingRecord()->importBatch;

        foreach ([
            BrandResource::getUrl('index'), BrandResource::getUrl('create'), BrandResource::getUrl('edit', ['record' => $this->theirs]),
            ProductResource::getUrl('index'), ProductResource::getUrl('create'), ProductResource::getUrl('edit', ['record' => $this->theirProduct]),
            FoodResource::getUrl('index'), FoodResource::getUrl('edit', ['record' => $food]),
            ImportBatchResource::getUrl('index'), ImportBatchResource::getUrl('edit', ['record' => $batch]),
            UploadImportBatch::getUrl(), SourceMatchingTools::getUrl(), CompareFoodSources::getUrl(),
            RecipeAnalysis::getUrl().'?product='.$this->theirProduct->id, Filament::getPanel('admin')->getUrl(),
        ] as $url) {
            $this->get($url)->assertOk();
        }
    }

    // ------------------------------------------------------------------ brand owner

    public function test_a_brand_owner_sees_only_their_brand_and_products(): void
    {
        $this->brandOwner();

        Livewire::test(ListBrands::class)
            ->assertCanSeeTableRecords([$this->mine])
            ->assertCanNotSeeTableRecords([$this->theirs]);
        Livewire::test(ListProducts::class)
            ->assertCanSeeTableRecords([$this->myProduct])
            ->assertCanNotSeeTableRecords([$this->theirProduct]);
    }

    public function test_another_brand_by_url_answers_not_found(): void
    {
        $this->brandOwner();

        $this->get(BrandResource::getUrl('edit', ['record' => $this->mine]))->assertOk();
        $this->get(BrandResource::getUrl('edit', ['record' => $this->theirs]))->assertNotFound();
        $this->get(ProductResource::getUrl('edit', ['record' => $this->myProduct]))->assertOk();
        $this->get(ProductResource::getUrl('edit', ['record' => $this->theirProduct]))->assertNotFound();
        $this->get(RecipeAnalysis::getUrl().'?product='.$this->theirProduct->id)->assertNotFound();
        $this->get(RecipeAnalysis::getUrl().'?product='.$this->myProduct->id)->assertOk();
    }

    public function test_a_brand_owner_cannot_create_brands_or_open_the_food_and_import_sections(): void
    {
        $this->brandOwner();

        $this->get(BrandResource::getUrl('create'))->assertForbidden();
        $this->get(FoodResource::getUrl('index'))->assertForbidden();
        $this->get(CompareFoodSources::getUrl())->assertForbidden();
        $this->get(ImportBatchResource::getUrl('index'))->assertForbidden();
        $this->get(UploadImportBatch::getUrl())->assertForbidden();
        $this->get(SourceMatchingTools::getUrl())->assertForbidden();
        $this->assertFalse(FoodDatabaseStats::canView());
    }

    public function test_a_forged_brand_id_is_refused(): void
    {
        $this->brandOwner();

        Livewire::test(CreateProduct::class)
            ->fillForm(['brand_id' => $this->theirs->id, 'name' => 'منتج مزروع', 'recipe' => ['servings' => 1, 'status' => 'draft']])
            ->call('create')
            ->assertHasFormErrors(['brand_id']);
        $this->assertSame(0, Product::where('name', 'منتج مزروع')->count());
    }

    public function test_recipes_take_approved_records_only(): void
    {
        $this->brandOwner();
        $pending = $this->pendingRecord();
        $gram = MeasureUnit::create(['code' => 'g', 'name_ar' => 'غرام', 'dimension' => 'mass', 'base_factor' => 1]);

        Livewire::test(RecipeIngredientsRelationManager::class, ['ownerRecord' => $this->myProduct, 'pageClass' => EditProduct::class])
            ->mountAction(TestAction::make(CreateAction::class)->table())
            ->fillForm(['food_source_record_id' => $pending->id, 'measure_unit_id' => $gram->id, 'amount' => 100])
            ->callMountedAction()
            ->assertHasFormErrors(['food_source_record_id']);
    }

    public function test_a_brand_owner_cannot_approve_official_nutrition_values(): void
    {
        $this->brandOwner();

        $this->assertFalse(Gate::allows('approveNutrition', $this->myProduct));
        Livewire::withQueryParams(['product' => $this->myProduct->id])
            ->test(RecipeAnalysis::class)
            ->call('approveAnalysis')
            ->assertForbidden();
    }

    // ------------------------------------------------------------------ nutritionist

    public function test_a_nutritionist_reviews_batches_and_nothing_else(): void
    {
        $this->signInAs(Access::NUTRITIONIST);

        $this->get(ImportBatchResource::getUrl('index'))->assertOk();
        $this->get(UploadImportBatch::getUrl())->assertForbidden();
        $this->get(SourceMatchingTools::getUrl())->assertForbidden();
        $this->get(FoodResource::getUrl('index'))->assertForbidden();
        $this->get(BrandResource::getUrl('index'))->assertForbidden();
        $this->assertFalse(Gate::allows('delete', ImportBatch::create(['original_filename' => 'c', 'file_path' => 'c', 'file_sha256' => str_repeat('c', 64)])));
        $this->assertFalse(Gate::allows('update', $this->myProduct));
        $this->assertTrue(Gate::allows('approveNutrition', $this->myProduct));
    }

    public function test_review_actions_record_who_and_a_rejection_needs_a_reason(): void
    {
        $nutritionist = $this->signInAs(Access::NUTRITIONIST);
        $record = $this->pendingRecord();
        $other = $this->pendingRecord();
        $page = ['ownerRecord' => $record->importBatch, 'pageClass' => EditImportBatch::class];

        Livewire::test(FoodSourceRecordsRelationManager::class, $page)
            ->callAction(TestAction::make('approve')->table($record));
        $record->refresh();
        $this->assertSame(FoodSourceStatus::Active, $record->status);
        $this->assertSame($nutritionist->id, (int) $record->reviewed_by);
        $this->assertNotNull($record->reviewed_at);

        $rm = Livewire::test(FoodSourceRecordsRelationManager::class, ['ownerRecord' => $other->importBatch, 'pageClass' => EditImportBatch::class]);
        $rm->callAction(TestAction::make('reject')->table($other), ['reason' => ''])
            ->assertHasFormErrors(['reason' => 'required']);
        $this->assertSame(FoodSourceStatus::PendingReview, $other->fresh()->status);

        Livewire::test(FoodSourceRecordsRelationManager::class, ['ownerRecord' => $other->importBatch, 'pageClass' => EditImportBatch::class])
            ->callAction(TestAction::make('reject')->table($other), ['reason' => 'قيمة الصوديوم غير منطقية']);
        $this->assertSame(FoodSourceStatus::Rejected, $other->fresh()->status);
        $this->assertStringContainsString('قيمة الصوديوم غير منطقية', $other->fresh()->notes);
    }

    public function test_a_brand_owner_cannot_review_records(): void
    {
        $this->brandOwner();

        $this->assertFalse(Gate::allows('review', $this->pendingRecord()));
    }

    // ------------------------------------------------------------------ accounts

    public function test_nobody_deletes_themselves_or_the_last_super_admin(): void
    {
        $admin = $this->signInAs();
        $this->assertFalse(Gate::allows('delete', $admin));
        $this->assertTrue(UserPolicy::isLastSuperAdmin($admin));

        $second = User::forceCreate(['name' => 'b', 'email' => 'b@example.test', 'password' => 'secret-password', 'is_active' => true]);
        $second->assignRole(Access::SUPER_ADMIN);
        $this->assertTrue(Gate::allows('delete', $second));
    }
}
