<?php

namespace Tests\Feature;

use App\Enums\FoodSourceStatus;
use App\Models\Brand;
use App\Models\DataSource;
use App\Models\Food;
use App\Models\FoodForm;
use App\Models\FoodSourceRecord;
use App\Models\MeasureUnit;
use App\Models\Product;
use App\Services\Site\Kitchen\KitchenRecipeFile;
use App\Services\Site\Kitchen\KitchenRecipeImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Kitchen recipe files (the owner's decisions 1a–4a): only what is new, never approved, never half a dish. */
class KitchenImportTest extends TestCase
{
    use RefreshDatabase;

    private const CSV = <<<'CSV'
dish,category,description,servings,weight_after_g,with_bread,ingredient,form,grams,after
حمص بطحينة,الفطور,حمص مسلوق مع طحينة.,4,458,1,حمص,boiled,330,
,,,,,,زيت زيتون,as_sold,14,1
فتوش,العشاء,سلطة.,2,300,,خس,raw,200,
,,,,,,سماق,as_sold,3,
CSV;

    private DataSource $foundation;

    private DataSource $sr;

    protected function setUp(): void
    {
        parent::setUp();
        MeasureUnit::create(['code' => 'g', 'name_ar' => 'غرام', 'dimension' => 'mass', 'base_factor' => 1]);
        $this->foundation = DataSource::create(['code' => 'usda_fdc_foundation', 'name' => 'Foundation', 'type' => 'usda_fdc', 'priority' => 90]);
        $this->sr = DataSource::create(['code' => 'usda_fdc_sr_legacy', 'name' => 'SR Legacy', 'type' => 'usda_fdc', 'priority' => 100]);
    }

    private function record(string $food, string $form, DataSource $source, FoodSourceStatus $status = FoodSourceStatus::Active): FoodSourceRecord
    {
        return FoodSourceRecord::create([
            'food_id' => Food::firstOrCreate(['name_ar' => $food])->id,
            'food_form_id' => FoodForm::firstOrCreate(['code' => $form], ['name_ar' => $form])->id,
            'data_source_id' => $source->id, 'external_ref' => uniqid(), 'status' => $status,
        ]);
    }

    public function test_the_file_reads_one_dish_per_name_with_its_lines(): void
    {
        $file = KitchenRecipeFile::parse("\xEF\xBB\xBF".self::CSV);

        $this->assertSame([], $file['problems']);
        $this->assertSame(['حمص بطحينة', 'فتوش'], array_column($file['dishes'], 'name'));
        $this->assertSame(['category' => 'الفطور', 'servings' => 4, 'weight_after_g' => 458.0, 'with_bread' => true],
            array_intersect_key($file['dishes'][0], array_flip(['category', 'servings', 'weight_after_g', 'with_bread'])));
        $this->assertSame([['food' => 'حمص', 'form' => 'boiled', 'grams' => 330.0, 'after' => false], ['food' => 'زيت زيتون', 'form' => 'as_sold', 'grams' => 14.0, 'after' => true]], $file['dishes'][0]['lines']);

        $this->assertNotEmpty(KitchenRecipeFile::parse("dish,grams\nx,1")['problems']['الملف']);
        $this->assertNotEmpty(KitchenRecipeFile::parse("dish,category,description,servings,weight_after_g,with_bread,ingredient,form,grams,after\nx,,,0,0,,a,b,1,")['problems']['x']);
    }

    public function test_dishes_are_added_unapproved_with_the_best_approved_record_and_only_once(): void
    {
        $this->record('حمص', 'boiled', $this->sr);
        $best = $this->record('حمص', 'boiled', $this->foundation);
        $this->record('حمص', 'raw', $this->foundation);                                        // another form: never used
        $this->record('زيت زيتون', 'as_sold', $this->sr);
        $this->record('زيت زيتون', 'as_sold', $this->foundation, FoodSourceStatus::PendingReview); // not approved: never used
        $this->record('خس', 'raw', $this->sr);

        $this->artisan('kitchen:import', ['section' => 'none'])->assertFailed();

        $report = app(KitchenRecipeImporter::class)->import(KitchenRecipeFile::parse(self::CSV)['dishes']);
        $this->assertSame(['added', 'skipped'], array_column($report, 'result'));
        $this->assertStringContainsString('سماق (as_sold)', $report[1]['reason']);   // never half a dish

        $dish = Product::where('name', 'حمص بطحينة')->with('recipe.ingredients', 'category')->sole();
        $this->assertSame([Brand::platform()->id, 'الفطور', true], [$dish->brand_id, $dish->category->name, $dish->eaten_with_bread]);
        $this->assertSame([4, 330.0, 458.0], [$dish->recipe->servings, (float) $dish->recipe->weight_before_cooking_g, (float) $dish->recipe->weight_after_cooking_g]);
        $this->assertSame($best->id, $dish->recipe->ingredients[0]->food_source_record_id);
        $this->assertTrue((bool) $dish->recipe->ingredients[1]->is_added_after_cooking);
        $this->assertNull($dish->recipe->nutrition_approved_at, 'the owner approves it');
        $this->assertSame(0, Product::where('name', 'فتوش')->count());

        // Run again, after the owner changed the dish: left as it is.
        $dish->recipe->update(['servings' => 5]);
        $this->assertSame('exists', app(KitchenRecipeImporter::class)->import(KitchenRecipeFile::parse(self::CSV)['dishes'])[0]['result']);
        $this->assertSame(5, $dish->recipe->fresh()->servings);
        $this->assertSame(1, Product::where('name', 'حمص بطحينة')->count());
    }

    public function test_every_kitchen_file_is_well_formed(): void
    {
        $files = glob(database_path('data/kitchen/*.csv'));
        $this->assertNotEmpty($files);

        $names = [];
        foreach ($files as $path) {
            $file = KitchenRecipeFile::parse((string) file_get_contents($path));
            $this->assertSame([], $file['problems'], basename($path));
            foreach ($file['dishes'] as $dish) {
                $before = array_sum(array_map(fn ($l) => $l['after'] ? 0 : $l['grams'], $dish['lines']));
                $this->assertGreaterThan(0, $before, $dish['name']);
                $this->assertNotNull($dish['description'], $dish['name']);
                $names[] = $dish['name'];
            }
        }
        $this->assertSame(array_unique($names), $names, 'one name a dish across the files');
        $this->assertCount(17, $names);   // breakfast 7, lunch 10
    }
}
