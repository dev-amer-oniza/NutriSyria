<?php

namespace Tests\Feature;

use App\Auth\Access;
use App\Enums\FoodSourceStatus;
use App\Filament\Clusters\ImportReview\Pages\UsdaSearch;
use App\Models\DataSource;
use App\Models\Food;
use App\Models\FoodForm;
use App\Models\FoodSourceRecord;
use App\Models\ImportBatch;
use App\Models\MeasureUnit;
use App\Models\MeasureUnitAlias;
use App\Models\Nutrient;
use App\Models\NutrientSourceCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/** The admin's live USDA search (the owner's decisions 1a 2a 3a 4a). */
class UsdaSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Cache::flush();
        config(['services.fdc.key' => 'test-key']);

        DataSource::create(['code' => 'usda_fdc_sr_legacy', 'name' => 'USDA SR Legacy', 'type' => 'usda_fdc']);
        DataSource::create(['code' => 'usda_fdc_foundation', 'name' => 'USDA Foundation Foods', 'type' => 'usda_fdc']);
        FoodForm::create(['code' => 'boiled', 'name_ar' => 'مسلوق']);
        foreach ([['energy_kcal', 'الطاقة', 'kcal', 'energy', 1008], ['protein_g', 'البروتين', 'g', 'proximate', 1003], ['fat_g', 'الدهون', 'g', 'proximate', 1004],
            ['carbohydrate_g', 'الكربوهيدرات', 'g', 'proximate', 1005], ['water_g', 'الماء', 'g', 'proximate', 1051], ['ash_g', 'الرماد', 'g', 'proximate', 1007]] as [$code, $name, $unit, $group, $fdc]) {
            $nutrient = Nutrient::create(['code' => $code, 'name_ar' => $name, 'name_en' => $code, 'unit' => $unit, 'group' => $group]);
            NutrientSourceCode::create(['nutrient_id' => $nutrient->id, 'source_system' => 'usda_fdc', 'code' => (string) $fdc, 'factor' => 1, 'priority' => 100]);
        }
        $cup = MeasureUnit::create(['code' => 'cup', 'name_ar' => 'كوب', 'dimension' => 'household']);
        MeasureUnitAlias::create(['measure_unit_id' => $cup->id, 'label' => 'cup']);

        Http::fake([
            '*/foods/search*' => Http::response(file_get_contents(__DIR__.'/../Fixtures/fdc/search.json')),
            '*/food/173757*' => Http::response(file_get_contents(__DIR__.'/../Fixtures/fdc/food-173757.json')),
        ]);
    }

    private function choice(array $more = []): array
    {
        return $more + ['target' => 'new', 'food_name_ar' => 'حمص', 'food_form_code' => 'boiled'];
    }

    public function test_only_the_admin_opens_the_page(): void
    {
        $this->signInAs();
        $this->get(UsdaSearch::getUrl())->assertOk()->assertSee('اسم الغذاء بالإنكليزية');
    }

    public function test_the_nutritionist_does_not(): void
    {
        $this->signInAs(Access::NUTRITIONIST);
        $this->get(UsdaSearch::getUrl())->assertForbidden();
    }

    public function test_a_search_lists_foundation_and_sr_legacy_foods_with_their_values(): void
    {
        $this->signInAs();

        Livewire::test(UsdaSearch::class)->set('query', 'chickpeas')->call('search')
            ->assertSee('Chickpeas (garbanzo beans, bengal gram), mature seeds, cooked, boiled, without salt')
            ->assertSee('SR Legacy')->assertSee('Foundation')
            ->assertSee('357')                       // Foundation has no "Energy": the Atwater figure stands in
            ->assertDontSee('A branded hummus');     // other releases are left out

        Http::assertSent(fn ($request) => $request->hasHeader('X-Api-Key', 'test-key')
            && $request['dataType'] === 'Foundation,SR Legacy' && ! str_contains($request->url(), 'test-key'));

        // The same search again comes from the cache.
        Livewire::test(UsdaSearch::class)->set('query', 'Chickpeas')->call('search');
        Http::assertSentCount(1);
    }

    public function test_adding_a_food_saves_it_pending_review_through_a_batch(): void
    {
        $admin = $this->signInAs();

        Livewire::test(UsdaSearch::class)->set('query', 'chickpeas')->call('search')
            ->call('add', 173757)
            ->assertActionMounted('addFood')
            ->setActionData($this->choice(['note' => 'مقاربة: حمص بلدي']))->callMountedAction()
            ->assertHasNoActionErrors()
            ->assertNotified('أُضيف بانتظار المراجعة');

        $record = FoodSourceRecord::with(['food', 'nutrientValues.nutrient', 'importBatch'])->sole();
        $this->assertSame(['حمص', '173757', FoodSourceStatus::PendingReview], [$record->food->name_ar, $record->external_ref, $record->status]);
        $this->assertStringStartsWith('مقاربة: حمص بلدي | FDC: Chickpeas', $record->notes);
        $this->assertEqualsWithDelta(164.0, (float) $record->nutrientValues->firstWhere('nutrient.code', 'energy_kcal')->amount_per_100g, 1e-6);
        $this->assertCount(6, $record->nutrientValues);
        $this->assertSame($admin->id, $record->importBatch->user_id);
        $this->assertStringStartsWith('USDA: Chickpeas', $record->importBatch->original_filename);
        $this->assertSame(1, ImportBatch::count());
    }

    public function test_a_food_already_in_the_database_is_pointed_out_not_added_again(): void
    {
        $this->signInAs();
        $page = Livewire::test(UsdaSearch::class)->set('query', 'chickpeas')->call('search');
        $page->call('add', 173757)->setActionData($this->choice())->callMountedAction()->assertHasNoActionErrors();

        Livewire::test(UsdaSearch::class)->set('query', 'chickpeas')->call('search')
            ->assertSee('موجود: حمص')
            ->call('add', 173757)
            ->assertActionNotMounted('addFood')
            ->assertNotified('هذا الغذاء موجود من قبل');

        $this->assertSame(1, FoodSourceRecord::count());
        $this->assertSame(1, Food::count());
    }

    public function test_it_can_be_added_to_a_food_we_already_have(): void
    {
        $this->signInAs();
        $food = Food::create(['name_ar' => 'حمص حب']);

        Livewire::test(UsdaSearch::class)->set('query', 'chickpeas')->call('search')
            ->call('add', 173757)
            ->setActionData(['target' => 'existing', 'food_id' => $food->id, 'food_form_code' => 'boiled'])->callMountedAction()
            ->assertHasNoActionErrors();

        $this->assertSame($food->id, FoodSourceRecord::sole()->food_id);
        $this->assertSame(1, Food::count());
    }

    public function test_the_details_show_our_nutrients_and_the_portions(): void
    {
        $this->signInAs();

        Livewire::test(UsdaSearch::class)->set('query', 'chickpeas')->call('search')
            ->mountAction('details', ['fdcId' => 173757])
            ->assertActionMounted('details');

        $preview = app(\App\Services\Usda\FdcImporter::class)->preview(173757);
        $this->assertSame(['name' => 'الطاقة', 'amount' => 164.0, 'unit' => 'kcal'], collect($preview['values'])->firstWhere('name', 'الطاقة'));
        $this->view('filament.clusters.import-review.components.usda-food', $preview + ['error' => null])
            ->assertSee('البروتين')->assertSee('8.86')->assertSee('الحصص (3)')->assertSee('164 g');
    }

    public function test_a_missing_key_and_a_spent_quota_are_explained(): void
    {
        $this->signInAs();

        config(['services.fdc.key' => null]);
        Livewire::test(UsdaSearch::class)->assertSee('مفتاح USDA غير موجود بعد')
            ->set('query', 'lentils')->call('search')->assertSee('أضف FDC_API_KEY');

        config(['services.fdc.key' => 'test-key']);
        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::fake(['*' => Http::response(['error' => ['code' => 'OVER_RATE_LIMIT']], 429)]);
        Livewire::test(UsdaSearch::class)->set('query', 'lentils')->call('search')->assertSee('تجاوزنا حد الطلبات');
    }
}
