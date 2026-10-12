<?php

namespace Tests\Feature;

use App\Models\DataSource;
use App\Models\FoodForm;
use App\Models\FoodPortion;
use App\Models\MeasureUnit;
use App\Models\MeasureUnitAlias;
use App\Models\Nutrient;
use App\Services\Import\BundleImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/** A re-import must never undo what the admin decided about portions. */
class BundleImporterPortionsTest extends TestCase
{
    use RefreshDatabase;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        DataSource::create(['code' => 'usda_fdc_sr_legacy', 'name' => 'USDA SR Legacy', 'type' => 'usda_fdc']);
        FoodForm::create(['code' => 'raw', 'name_ar' => 'نيء']);
        Nutrient::create(['code' => 'energy_kcal', 'name_ar' => 'الطاقة', 'name_en' => 'Energy', 'unit' => 'kcal', 'group' => 'energy']);
        $cup = MeasureUnit::create(['code' => 'cup', 'name_ar' => 'كوب', 'dimension' => 'household']);
        $piece = MeasureUnit::create(['code' => 'piece', 'name_ar' => 'حبة', 'dimension' => 'count']);
        MeasureUnitAlias::create(['measure_unit_id' => $cup->id, 'label' => 'cup']);
        foreach (['piece', 'small', 'medium', 'large'] as $label) {
            MeasureUnitAlias::create(['measure_unit_id' => $piece->id, 'label' => $label]);
        }

        $this->dir = sys_get_temp_dir().'/bundle-'.uniqid();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);
        parent::tearDown();
    }

    /** Writes a tomato bundle and runs stage -> validate -> commit. */
    private function import(?array $portions, float $kcal = 18): array
    {
        File::deleteDirectory($this->dir);
        File::ensureDirectoryExists($this->dir);
        $csv = function (string $name, array $rows) {
            $handle = fopen("{$this->dir}/{$name}", 'w');
            foreach ($rows as $row) {
                fputcsv($handle, $row, ',', '"', '');
            }
            fclose($handle);
        };

        $csv('records.csv', [
            ['food_id', 'food_name_ar', 'food_name_en', 'category_path', 'food_form_code', 'data_source_code', 'external_ref', 'notes'],
            ['', 'بندورة', 'Tomato', '', 'raw', 'usda_fdc_sr_legacy', '170457', ''],
        ]);
        $csv('nutrient_values.csv', [
            ['data_source_code', 'external_ref', 'nutrient_code', 'amount_per_100g', 'unit', 'method'],
            ['usda_fdc_sr_legacy', '170457', 'energy_kcal', (string) $kcal, 'kcal', 'imported'],
        ]);
        if ($portions !== null) {
            $csv('portions.csv', array_merge(
                [['data_source_code', 'external_ref', 'measure_unit_label', 'amount', 'gram_weight', 'description', 'modifier', 'basis']],
                array_map(fn ($p) => ['usda_fdc_sr_legacy', '170457', $p[0], '1', (string) $p[1], '', $p[0], 'usda'], $portions),
            ));
        }

        $importer = new BundleImporter();
        $batch = $importer->stage($this->dir, null, true);
        $importer->validate($batch);

        return $importer->commit($batch);
    }

    private function portion(string $sourceLabel): ?FoodPortion
    {
        return FoodPortion::all()->first(fn (FoodPortion $p) => ($p->evidence['source_label'] ?? null) === $sourceLabel);
    }

    private const TOMATO = [['cup, chopped', 180], ['cup, cherry', 149], ['small', 91], ['medium', 123]];

    public function test_size_words_are_kept_as_the_portion_description(): void
    {
        $this->import(self::TOMATO);

        $this->assertSame('small', $this->portion('small')->description);
        $this->assertSame('cup, chopped', $this->portion('cup, chopped')->description);
    }

    public function test_reimport_keeps_the_admin_default_and_corrected_weight(): void
    {
        $this->import(self::TOMATO);
        $chopped = $this->portion('cup, chopped');
        $chopped->update(['is_default' => true]);
        $this->portion('medium')->update(['gram_weight' => 130]);   // measured locally

        $stats = $this->import(self::TOMATO, kcal: 19);

        $this->assertSame(4, $stats['portions_updated']);
        $this->assertSame(0, $stats['portions_written']);
        $this->assertSame($chopped->id, $this->portion('cup, chopped')->id);   // same row, updated in place
        $this->assertTrue($this->portion('cup, chopped')->is_default);
        $this->assertEqualsWithDelta(130.0, (float) $this->portion('medium')->gram_weight, 1e-6);
    }

    public function test_reimport_applies_a_new_source_weight_the_admin_never_touched(): void
    {
        $this->import(self::TOMATO);

        $this->import([['cup, chopped', 182], ['cup, cherry', 149], ['small', 91], ['medium', 123]]);

        $this->assertEqualsWithDelta(182.0, (float) $this->portion('cup, chopped')->gram_weight, 1e-6);
    }

    public function test_a_bundle_without_portions_leaves_the_stored_portions_alone(): void
    {
        $this->import(self::TOMATO);

        $this->import(null, kcal: 19);

        $this->assertSame(4, FoodPortion::count());
    }

    public function test_a_portion_the_source_dropped_is_removed_unless_the_admin_relied_on_it(): void
    {
        $this->import(self::TOMATO);
        $this->portion('small')->update(['is_default' => true]);

        $stats = $this->import([['cup, chopped', 180], ['medium', 123]]);

        $this->assertNull($this->portion('cup, cherry'));      // dropped by the source: removed
        $this->assertNotNull($this->portion('small'));         // dropped too, but it is the admin's default
        $this->assertSame(1, $stats['portions_kept']);
    }

    public function test_portions_added_by_hand_are_never_touched(): void
    {
        $this->import(self::TOMATO);
        $record = $this->portion('small')->food_source_record_id;
        FoodPortion::create([
            'food_id' => $this->portion('small')->food_id, 'measure_unit_id' => MeasureUnit::where('code', 'cup')->value('id'),
            'amount' => 1, 'gram_weight' => 200, 'description' => 'كوب مطبخنا', 'food_source_record_id' => $record,
        ]);

        $this->import([['cup, chopped', 180]]);

        $this->assertSame(1, FoodPortion::where('description', 'كوب مطبخنا')->count());
    }
}
