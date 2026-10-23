<?php

namespace Tests\Feature;

use App\Auth\Access;
use App\Enums\FoodSourceStatus;
use App\Filament\Clusters\FoodDatabase\Resources\Food\Resources\FoodSourceRecords\FoodSourceRecordResource;
use App\Filament\Clusters\FoodDatabase\Resources\Food\Resources\FoodSourceRecords\Pages\CreateFoodSourceRecord;
use App\Filament\Clusters\ReferenceSettings\Resources\DataSources\Pages\EditDataSource;
use App\Filament\Clusters\ReferenceSettings\Resources\DataSources\Pages\ListDataSources;
use App\Exceptions\ImportFileRejected;
use App\Filament\Clusters\ImportReview\Pages\UploadImportBatch;
use App\Filament\Clusters\Brands\Resources\Products\Pages\EditProduct;
use App\Models\Brand;
use App\Models\DataSource;
use App\Models\MeasureUnit;
use App\Models\Product;
use App\Models\ProductRecipe;
use App\Models\ProductRecipeIngredient;
use App\Services\Nutrition\GramsConverter;
use App\Services\Import\BundleImporter;
use App\Models\Food;
use App\Models\FoodForm;
use App\Models\FoodNutrientValue;
use App\Models\FoodSourceRecord;
use App\Models\Nutrient;
use App\Filament\Clusters\Brands\Resources\Products\RelationManagers\RecipeIngredientsRelationManager;
use ReflectionMethod;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/** The panel's known errors, each now a clear message (or simply working). */
class PanelFixesTest extends TestCase
{
    use RefreshDatabase;

    private DataSource $source;

    private FoodForm $raw;

    protected function setUp(): void
    {
        parent::setUp();
        $this->source = DataSource::create(['code' => 'usda_fdc_sr_legacy', 'name' => 'USDA SR Legacy', 'type' => 'usda_fdc']);
        $this->raw = FoodForm::create(['code' => 'raw', 'name_ar' => 'نيء', 'name_en' => 'raw']);
    }

    public function test_a_source_record_is_added_from_the_food_page(): void
    {
        $this->signInAs(Access::SUPER_ADMIN);
        $chickpeas = Food::create(['name_ar' => 'حمص']);

        $this->get(FoodSourceRecordResource::getUrl('create', ['food' => $chickpeas]))->assertOk();

        Livewire::test(CreateFoodSourceRecord::class, ['parentRecord' => $chickpeas])
            ->fillForm(['food_form_id' => $this->raw->id, 'data_source_id' => $this->source->id, 'external_ref' => '173756', 'status' => FoodSourceStatus::PendingReview->value])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame($chickpeas->id, FoodSourceRecord::where('external_ref', '173756')->value('food_id'));
    }

    public function test_a_duplicate_source_item_gets_a_clear_message(): void
    {
        $this->signInAs(Access::SUPER_ADMIN);
        $chickpeas = Food::create(['name_ar' => 'حمص']);
        $lentils = Food::create(['name_ar' => 'عدس']);
        FoodSourceRecord::create(['food_id' => $chickpeas->id, 'data_source_id' => $this->source->id, 'external_ref' => '173756', 'status' => FoodSourceStatus::Active]);

        Livewire::test(CreateFoodSourceRecord::class, ['parentRecord' => $lentils])
            ->fillForm(['food_form_id' => $this->raw->id, 'data_source_id' => $this->source->id, 'external_ref' => '173756', 'status' => FoodSourceStatus::PendingReview->value])
            ->call('create')
            ->assertHasFormErrors(['external_ref'])
            ->assertSee('هذا الرقم موجود في نفس المصدر لغذاء «حمص»');

        $this->assertSame(1, FoodSourceRecord::count());
    }

    public function test_a_source_holding_records_is_not_deleted(): void
    {
        $this->signInAs(Access::SUPER_ADMIN);
        FoodSourceRecord::create(['food_id' => Food::create(['name_ar' => 'حمص'])->id, 'data_source_id' => $this->source->id, 'external_ref' => '1', 'status' => FoodSourceStatus::Active]);
        $unused = DataSource::create(['code' => 'lab_x', 'name' => 'مخبر تجريبي', 'type' => 'lab']);

        Livewire::test(EditDataSource::class, ['record' => $this->source->id])
            ->callAction('delete')
            ->assertNotified('لا يمكن حذف «USDA SR Legacy»: عليه 1 سجلاً، وكل رقم معتمد يحتاج مصدره. أوقفه بدل حذفه إن لم تعد تستعمله.');
        $this->assertModelExists($this->source);

        Livewire::test(ListDataSources::class)
            ->selectTableRecords([$this->source, $unused])
            ->callAction(TestAction::make('delete')->table()->bulk());
        $this->assertModelExists($this->source);
        $this->assertModelExists($unused);

        Livewire::test(EditDataSource::class, ['record' => $unused->id])->callAction('delete');
        $this->assertModelMissing($unused);
    }

    public function test_the_ingredient_picker_puts_trusted_sources_first_and_marks_incomplete_records(): void
    {
        $foundation = DataSource::create(['code' => 'usda_fdc_foundation', 'name' => 'USDA Foundation', 'type' => 'usda_fdc', 'priority' => 90]);
        $this->source->update(['priority' => 100]);
        $codes = [];
        foreach (['energy_kcal', 'protein_g', 'fat_g', 'carbohydrate_g'] as $code) {
            $codes[$code] = Nutrient::create(['code' => $code, 'name_ar' => $code, 'name_en' => $code, 'unit' => 'g', 'group' => 'proximate', 'is_core' => true]);
        }
        $record = function (DataSource $source, array $values) use ($codes) {
            $r = FoodSourceRecord::create(['food_id' => Food::firstOrCreate(['name_ar' => 'حمص'])->id, 'food_form_id' => $this->raw->id,
                'data_source_id' => $source->id, 'external_ref' => (string) random_int(1, 999999), 'status' => FoodSourceStatus::Active]);
            foreach ($values as $code) {
                FoodNutrientValue::create(['food_source_record_id' => $r->id, 'nutrient_id' => $codes[$code]->id, 'amount_per_100g' => 1, 'unit' => 'g']);
            }

            return $r;
        };
        $legacy = $record($this->source, ['energy_kcal', 'fat_g', 'carbohydrate_g']);   // no protein
        $modern = $record($foundation, ['protein_g', 'fat_g', 'carbohydrate_g']);       // no calories: calculated, fine

        $options = (new ReflectionMethod(RecipeIngredientsRelationManager::class, 'ingredientOptions'))->invoke(null);

        $this->assertSame([$modern->id, $legacy->id], array_keys($options), 'Foundation before SR Legacy');
        $this->assertSame('حمص — نيء (USDA Foundation)', $options[$modern->id]);
        $this->assertSame('حمص — نيء (USDA SR Legacy) · ناقص: البروتين', $options[$legacy->id]);
    }

    /** A bundle folder with the given sheets (file name => content). */
    private function bundle(array $files): string
    {
        $dir = storage_path('framework/testing/bundle-'.uniqid());
        File::ensureDirectoryExists($dir);
        foreach ($files as $name => $content) {
            file_put_contents("{$dir}/{$name}", $content);
        }

        return $dir;
    }

    private function rejection(array $files): string
    {
        try {
            (new BundleImporter)->stage($this->bundle($files));
        } catch (ImportFileRejected $e) {
            $this->assertSame(0, DB::table('import_batches')->count(), 'a bad file leaves no batch');

            return $e->getMessage();
        }

        $this->fail('The bundle was accepted.');
    }

    public function test_a_bad_import_file_is_refused_with_its_reason_and_leaves_no_batch(): void
    {
        $values = "data_source_code,external_ref,nutrient_code,amount_per_100g,unit\nusda_fdc_sr_legacy,1,protein_g,8,g\n";

        $this->assertSame('ملف السجلات فارغ: لا يحتوي حتى سطر العناوين.', $this->rejection(['records.csv' => '', 'nutrient_values.csv' => $values]));
        $this->assertSame('ملف السجلات فارغ: لا يحتوي حتى سطر العناوين.', $this->rejection(['records.csv' => "\xEF\xBB\xBF\n", 'nutrient_values.csv' => $values]));
        $this->assertStringStartsWith('ملف السجلات: ينقصه عمود external_ref، food_name_ar (أو food_id).',
            $this->rejection(['records.csv' => "data_source_code,food_form_code\nusda_fdc_sr_legacy,raw\n", 'nutrient_values.csv' => $values]));
        $this->assertSame('ملف السجلات: لا يحتوي أي سطر بعد العناوين.',
            $this->rejection(['records.csv' => "data_source_code,external_ref,food_form_code,food_name_ar\n", 'nutrient_values.csv' => $values]));
        $this->assertStringStartsWith('ملف القيم الغذائية: السطر 3 فيه 3 أعمدة، والعناوين 5.',
            $this->rejection(['records.csv' => "data_source_code,external_ref,food_form_code,food_name_ar\nusda_fdc_sr_legacy,1,raw,حمص\n", 'nutrient_values.csv' => $values."usda_fdc_sr_legacy,1,fat_g\n"]));
    }

    public function test_a_semicolon_separated_file_is_read_as_is(): void
    {
        $batchId = (new BundleImporter)->stage($this->bundle([
            'records.csv' => "\xEF\xBB\xBFdata_source_code;external_ref;food_form_code;food_name_ar\r\nusda_fdc_sr_legacy;173756;raw;حمص\r\n",
            'nutrient_values.csv' => "data_source_code\texternal_ref\tnutrient_code\tamount_per_100g\tunit\nusda_fdc_sr_legacy\t173756\tprotein_g\t8,5\tg\n",
        ]));

        $rows = DB::table('import_rows')->where('import_batch_id', $batchId)->orderBy('id')->pluck('raw')->map(fn ($r) => json_decode($r, true));
        $this->assertSame(['data_source_code' => 'usda_fdc_sr_legacy', 'external_ref' => '173756', 'food_form_code' => 'raw', 'food_name_ar' => 'حمص'], $rows[0]);
        $this->assertSame('protein_g', $rows[1]['nutrient_code']);
    }

    public function test_the_upload_page_shows_the_reason_and_keeps_no_batch(): void
    {
        Storage::fake('local');
        $this->signInAs(Access::SUPER_ADMIN);

        Livewire::test(UploadImportBatch::class)
            ->fillForm([
                'batch_label' => 'دفعة تجريبية',
                'records_file' => UploadedFile::fake()->createWithContent('records.csv', "data_source_code,food_form_code\nusda_fdc_sr_legacy,raw\n"),
                'nutrient_values_file' => UploadedFile::fake()->createWithContent('nutrient_values.csv', "data_source_code,external_ref,nutrient_code,amount_per_100g,unit\n"),
            ])
            ->call('validateBundle')
            ->assertSet('batchId', null)
            ->assertSee('ينقصه عمود external_ref')
            ->assertNotified('تعذّر فحص الملفات');

        $this->assertSame(0, DB::table('import_batches')->count());
    }

    public function test_the_reason_a_line_is_not_converted_shows_on_hover(): void
    {
        $this->signInAs(Access::SUPER_ADMIN);
        $tomato = FoodSourceRecord::create(['food_id' => Food::create(['name_ar' => 'بندورة'])->id, 'food_form_id' => $this->raw->id,
            'data_source_id' => $this->source->id, 'external_ref' => '170457', 'status' => FoodSourceStatus::Active]);
        $cup = MeasureUnit::create(['code' => 'cup', 'name_ar' => 'كوب', 'dimension' => 'count']);
        $product = Product::create(['brand_id' => Brand::create(['name' => 'مطعم'])->id, 'name' => 'سلطة']);
        $recipe = ProductRecipe::create(['product_id' => $product->id, 'servings' => 1]);
        ProductRecipeIngredient::create(['product_recipe_id' => $recipe->id, 'food_source_record_id' => $tomato->id, 'measure_unit_id' => $cup->id, 'amount' => 1, 'sort_order' => 1]);

        $reason = app(GramsConverter::class)->resolve($tomato->id, $cup->id, 1)->message;
        $this->assertNotEmpty($reason);

        Livewire::test(RecipeIngredientsRelationManager::class, ['ownerRecord' => $product, 'pageClass' => EditProduct::class])
            ->assertSee('⚠ غير متوفر')
            // The tooltip carries the reason, escaped for JavaScript (\u0644…).
            ->assertSee(trim(json_encode(mb_substr($reason, 0, 12)), '"'), false);
    }
}
