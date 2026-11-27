<?php

namespace Tests\Feature;

use App\Auth\Access;
use App\Filament\Clusters\ImportReview\Pages\AddFoods;
use App\Filament\Clusters\ImportReview\Pages\SourceMatchingTools;
use App\Filament\Clusters\ImportReview\Pages\UploadImportBatch;
use App\Filament\Clusters\ImportReview\Pages\UsdaSearch;
use App\Filament\Clusters\ImportReview\Resources\ImportBatches\ImportBatchResource;
use App\Models\DataSource;
use App\Models\FoodForm;
use App\Models\Nutrient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/** "إضافة أغذية": one entry for the three ways food data comes in, each ending in the review queue. */
class AddFoodsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_admin_sees_the_three_ways(): void
    {
        $this->signInAs();

        $this->get(AddFoods::getUrl())
            ->assertOk()
            ->assertSee('غذاء واحد')->assertSee('قائمة أغذية (مطابقة)')->assertSee('ملفات جاهزة')
            ->assertSee(UsdaSearch::getUrl(), false)
            ->assertSee(SourceMatchingTools::getUrl(), false)
            ->assertSee(UploadImportBatch::getUrl(), false);
    }

    public function test_matching_and_upload_are_steps_not_menu_items(): void
    {
        $this->assertTrue(AddFoods::shouldRegisterNavigation());
        $this->assertFalse(SourceMatchingTools::shouldRegisterNavigation());
        $this->assertFalse(UploadImportBatch::shouldRegisterNavigation());

        // On either step the menu still marks "إضافة أغذية".
        $this->assertContains(SourceMatchingTools::getRouteName(), (array) AddFoods::getNavigationItemActiveRoutePattern());
        $this->assertContains(UploadImportBatch::getRouteName(), (array) AddFoods::getNavigationItemActiveRoutePattern());
    }

    public function test_the_reviewer_reviews_but_does_not_add(): void
    {
        $this->signInAs(Access::REVIEWER);

        $this->get(AddFoods::getUrl())->assertForbidden();
        $this->get(ImportBatchResource::getUrl('index'))->assertOk();
    }

    public function test_matching_picks_the_source_once_with_foundation_and_sr_legacy_preselected(): void
    {
        $root = 'storage/framework/testing/fdc-'.uniqid();
        foreach (['FoodData_Central_foundation_food_csv_2025-04-24', 'FoodData_Central_foundation_food_csv_2026-04-30', 'FoodData_Central_survey_food_csv_2024-10-31', 'sr_legacy'] as $release) {
            File::ensureDirectoryExists(base_path("{$root}/{$release}"));
        }
        config(['source_adapters' => ['usda_fdc' => ['label' => 'USDA', 'script' => 'usda_to_template.py', 'releases_path' => $root]]]);
        $this->signInAs();

        try {
            Livewire::test(SourceMatchingTools::class)
                ->assertSet('sourceData.adapter', 'usda_fdc')
                // The newest Foundation and SR Legacy; never FNDDS, never an older Foundation.
                ->assertSet('sourceData.release', ['FoodData_Central_foundation_food_csv_2026-04-30', 'sr_legacy'])
                ->assertDontSee('لا توجد نسخة مفكوكة الضغط')
                ->assertSee('الطريقة')->assertSee('المطابقة');
        } finally {
            File::deleteDirectory(base_path($root));
        }
    }

    public function test_ready_files_are_checked_then_saved_straight_into_the_review_queue(): void
    {
        Storage::fake('local');
        DataSource::create(['code' => 'usda_fdc_sr_legacy', 'name' => 'USDA SR Legacy', 'type' => 'usda_fdc']);
        FoodForm::create(['code' => 'raw', 'name_ar' => 'نيء']);
        Nutrient::create(['code' => 'energy_kcal', 'name_ar' => 'الطاقة', 'name_en' => 'Energy', 'unit' => 'kcal', 'group' => 'energy']);
        $this->signInAs();

        $page = Livewire::test(UploadImportBatch::class)
            ->assertDontSee('فتح دفعة سابقة')
            ->fillForm([
                'batch_label' => 'سماق',
                'records_file' => UploadedFile::fake()->createWithContent('records.csv', "food_name_ar,food_form_code,data_source_code,external_ref\nسماق,raw,usda_fdc_sr_legacy,999001\n"),
                'nutrient_values_file' => UploadedFile::fake()->createWithContent('nutrient_values.csv', "data_source_code,external_ref,nutrient_code,amount_per_100g,unit\nusda_fdc_sr_legacy,999001,energy_kcal,239,kcal\n"),
            ])
            ->call('validateBundle')
            ->assertSee('نتيجة الفحص')
            ->assertSee('احفظ وافتح المراجعة (1 سجل)');

        $batchId = $page->get('batchId');
        $page->call('commitBundle')
            ->assertRedirect(ImportBatchResource::getUrl('edit', ['record' => $batchId]));

        $this->assertSame('committed', DB::table('import_batches')->where('id', $batchId)->value('status'));
    }

    public function test_starting_over_drops_the_unsaved_batch(): void
    {
        Storage::fake('local');
        DataSource::create(['code' => 'usda_fdc_sr_legacy', 'name' => 'USDA SR Legacy', 'type' => 'usda_fdc']);
        FoodForm::create(['code' => 'raw', 'name_ar' => 'نيء']);
        Nutrient::create(['code' => 'energy_kcal', 'name_ar' => 'الطاقة', 'name_en' => 'Energy', 'unit' => 'kcal', 'group' => 'energy']);
        $this->signInAs();

        Livewire::test(UploadImportBatch::class)
            ->fillForm([
                'batch_label' => 'سماق',
                'records_file' => UploadedFile::fake()->createWithContent('records.csv', "food_name_ar,food_form_code,data_source_code,external_ref\nسماق,raw,usda_fdc_sr_legacy,999001\n"),
                'nutrient_values_file' => UploadedFile::fake()->createWithContent('nutrient_values.csv', "data_source_code,external_ref,nutrient_code,amount_per_100g,unit\nusda_fdc_sr_legacy,999001,energy_kcal,239,kcal\n"),
            ])
            ->call('validateBundle')
            ->call('startOver')
            ->assertRedirect(UploadImportBatch::getUrl());

        $this->assertSame(0, DB::table('import_batches')->count());
    }

    public function test_a_batch_built_by_matching_shows_matching_as_done(): void
    {
        $this->signInAs();
        $batchId = DB::table('import_batches')->insertGetId([
            'original_filename' => 'مطابقة', 'file_path' => 'x', 'file_sha256' => str_repeat('d', 64), 'status' => 'validated',
            'stats' => json_encode(['validation' => ['records' => ['ready' => 0, 'blocked' => 0, 'new_foods' => 0], 'values' => ['ok' => 0, 'total' => 0], 'portions' => ['ok' => 0, 'total' => 0], 'issues' => []]]),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        Livewire::withQueryParams(['batch' => $batchId, 'from' => 'matching'])
            ->test(UploadImportBatch::class)
            ->assertSet('fromMatching', true)
            ->assertSee('المطابقة')
            ->assertSee('نتيجة الفحص');
    }
}
