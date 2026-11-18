<?php

namespace Tests\Unit;

use App\Enums\EnergyEquation;
use App\Enums\Sex;
use PHPUnit\Framework\TestCase;

/**
 * The third gate of the references (config/nutrition_references.php): the numbers the code
 * applies are the numbers the references say, word for word where the quote holds them.
 * A failure here means a rule and its source no longer agree: fix the rule, or read the
 * source again, never the test alone.
 */
class NutritionReferencesTest extends TestCase
{
    private array $rules;

    private array $refs;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rules = require __DIR__.'/../../config/nutrition_criteria.php';
        $this->refs = require __DIR__.'/../../config/nutrition_references.php';
    }

    private function quote(string $ref): string
    {
        return $this->refs[$ref]['quote'];
    }

    public function test_every_named_reference_exists_and_was_read(): void
    {
        $named = [
            ...$this->rules['daily_life_refs'], ...$this->rules['exercise_refs'], ...$this->rules['protein_refs'],
            ...$this->rules['goal_refs'], ...$this->rules['min_kcal_refs'], ...$this->rules['bmi']['refs'],
        ];
        foreach ($this->rules['criteria'] as $criterion) {
            $named = [...$named, ...($criterion['refs'] ?? [])];
        }
        foreach ($this->rules['templates'] as $template) {
            $named = [...$named, ...($template['refs'] ?? [])];
            foreach ($template['rules'] ?? [] as $rule) {
                $named = [...$named, ...($rule['refs'] ?? [])];
            }
        }

        foreach (array_unique($named) as $key) {
            $this->assertArrayHasKey($key, $this->refs, $key);
            foreach (['org', 'title', 'year', 'url', 'where', 'quote', 'summary', 'applies', 'read_at', 'gates'] as $field) {
                $this->assertNotEmpty($this->refs[$key][$field] ?? null, "$key.$field");
            }
            $this->assertTrue($this->refs[$key]['gates']['source'], "$key was read");
            $this->assertTrue($this->refs[$key]['gates']['test'], "$key is tested here");
            $this->assertStringStartsWith('https://', $this->refs[$key]['url']);
        }
    }

    public function test_sodium_is_whos_2000_mg(): void
    {
        $this->assertSame(2000, $this->rules['criteria']['sodium_mg']['default']['value']);
        $this->assertStringContainsString('less than 2000 mg/day of sodium', $this->quote('who_sodium'));
        $this->assertSame('guidance', $this->rules['criteria']['sodium_mg']['kind'], 'two references disagree (2000, 2300): advice, not a strict limit');
        $this->assertStringContainsString('2,300 mg', $this->quote('dga_2025_sodium'));
    }

    public function test_saturated_fat_is_10_percent_of_energy(): void
    {
        $this->assertSame(10, $this->rules['criteria']['fat_sat_g']['default']['pct']);
        $this->assertStringContainsString('no more than 10% of total energy intake coming from saturated fatty acids', $this->quote('who_fats_2023'));
        $this->assertStringContainsString('should not exceed 10% of total daily calories', $this->quote('dga_2025_saturated'));
    }

    public function test_fibre_is_14_g_per_1000_kcal_and_a_shortfall_only(): void
    {
        $this->assertSame(['type' => 'per_1000', 'value' => 14], $this->rules['criteria']['fiber_g']['default']);
        $this->assertStringContainsString('14g/1,000 kcal', $this->quote('dga_2020_appendix'));
        $this->assertSame('shortfall', $this->rules['criteria']['fiber_g']['kind']);
    }

    public function test_the_least_protein_is_the_rda(): void
    {
        $rule = $this->rules['criteria']['protein_min_g'];

        $this->assertSame(['type' => 'by_sex', 'female' => 46, 'male' => 56], $rule['default']);
        $this->assertStringContainsString('46 (females 19+), 56 (males 19+)', $this->quote('dga_2020_appendix'));
        $this->assertSame(['min', 'shortfall'], [$rule['bound'], $rule['kind']]);
    }

    public function test_total_sugars_are_information_only(): void
    {
        $this->assertTrue($this->rules['criteria']['sugars_g']['info_only']);
        $this->assertStringContainsString('free sugars', $this->quote('who_free_sugars_2015'));
        $this->assertFalse($this->refs['who_free_sugars_2015']['gates']['applicability'], 'free sugars are not our total sugars');
    }

    public function test_the_macro_rule_stays_inside_the_reference_ranges(): void
    {
        $rule = $this->rules['macro_rule'];

        // NASEM: fat 20–35%, carbohydrate 45–65%, protein 10–35%; WHO: fat 30% or less.
        $this->assertStringContainsString('Adults: Fat 20–35 · Carbohydrate 45–65 · Protein 10–35', $this->quote('nasem_amdr'));
        $this->assertStringContainsString('limit total fat intake to 30% of total energy intake or less', $this->quote('who_fats_2023'));
        $this->assertSame([30, 20, 45, 35], [$rule['fat_pct'], $rule['fat_min_pct'], $rule['carbs_min_pct'], $rule['protein_max_pct']]);
    }

    public function test_protein_per_kg_is_inside_the_guidelines_range(): void
    {
        $this->assertStringContainsString('1.2–1.6 grams of protein per kilogram of body weight per day', $this->quote('dga_2025_protein'));

        foreach ($this->rules['templates'] as $key => $template) {
            foreach ((array) $template['protein_per_kg'] as $perKg) {
                $this->assertTrue($perKg >= 1.2 && $perKg <= 1.6, "$key $perKg");
            }
        }
    }

    public function test_the_energy_equation_is_mifflins(): void
    {
        $this->assertStringContainsString('REE (males) = 10 x weight (kg) + 6.25 x height (cm) - 5 x age (y) + 5', $this->quote('mifflin_1990'));
        $this->assertStringContainsString('REE (females) = 10 x weight (kg) + 6.25 x height (cm) - 5 x age (y) - 161', $this->quote('mifflin_1990'));

        $this->assertSame(10 * 80 + 6.25 * 178 - 5 * 30 + 5, EnergyEquation::Mifflin->bmr(Sex::Male, 30, 178, 80));
        $this->assertSame(10 * 60 + 6.25 * 160 - 5 * 45 - 161, EnergyEquation::Mifflin->bmr(Sex::Female, 45, 160, 60));
    }

    public function test_the_day_factors_come_from_fao(): void
    {
        $quote = $this->quote('fao_energy_2004');
        $factors = $this->rules['daily_life'];

        // Seated work: FAO's example of someone who does not exercise, 36.7 ÷ 24.
        $this->assertStringContainsString('Total 24 36.7 36.7/24 = 1.53', $quote);
        $this->assertSame(1.53, $factors['seated']);
        // Standing work: FAO's waiter, without its hour of exercise (4.2) and with that hour seated (1.4).
        $this->assertStringContainsString('Total 24 42.2 42.2/24 = 1.76', $quote);
        $this->assertSame(round((42.2 - 4.2 + 1.4) / 24, 2), $factors['standing']);
        // At home: the lowest a sustained day reaches; physical work: inside the masons' 1.70–1.99.
        $this->assertStringContainsString('range from about 1.40 to 2.40', $quote);
        $this->assertSame(1.40, $factors['home']);
        $this->assertStringContainsString('Active or moderately active lifestyle 1.70-1.99', $quote);
        $this->assertTrue($factors['physical'] >= 1.70 && $factors['physical'] <= 1.99);
        $this->assertSame([1.40, 2.40], $this->rules['pal_range']);
    }

    public function test_every_exercise_reads_its_met_from_the_compendium(): void
    {
        $quote = $this->quote('compendium_2024');

        $this->assertStringContainsString('1 MET = 1 kcal/kg/hr', $quote);
        foreach ($this->rules['exercises'] as $key => $exercise) {
            $this->assertMatchesRegularExpression('/'.$exercise['code'].' '.preg_quote(number_format($exercise['met'], 1), '/').' /', $quote, $key);
        }
        $this->assertSame(1.4, $this->rules['replaced_par'], 'the hour counted as seated is taken off (FAO: 1.4 for light leisure)');
    }

    public function test_the_deficit_and_the_floor_follow_the_obesity_guideline(): void
    {
        $quote = $this->quote('aha_obesity_2013');

        $this->assertSame(-500, $this->rules['goal_adjustment']['lose']);
        $this->assertStringContainsString('Prescribe a 500-kcal/d or 750-kcal/d energy deficit', $quote);
        $this->assertStringContainsString('Many used a 500 to 800 kilocalories a day deficit', $this->quote('nice_obesity_ng246'));
        // Under the minimum is a specialist's low-energy diet, not ours.
        $this->assertStringContainsString('Consider low-energy diets (800 to 1,200 kilocalories per day', $this->quote('nice_obesity_ng246'));
        $this->assertArrayNotHasKey('deficit_min_bmi', $this->rules, 'a normal weight may lose too (decision 2a)');

        $this->assertSame(['female' => 1200, 'male' => 1500], $this->rules['min_kcal']);
        $this->assertStringContainsString('1200–1500 kcal/d for women and 1500–1800 kcal/d for men', $quote);

        $this->assertSame(300, $this->rules['goal_adjustment']['gain'], 'ours, said so on the sources page (decision 4a)');
    }

    public function test_the_body_mass_index_classes_read_from_who(): void
    {
        $below = array_column($this->rules['bmi']['classes'], 'below', 'key');

        $this->assertSame([18.5, 25, 30], [$below['underweight'], $below['normal'], $below['overweight']]);
        $this->assertStringContainsString('BMI < 18.5', $this->quote('who_bmi'));
        $this->assertStringContainsString('obesity is a BMI greater than or equal to 30', $this->quote('who_bmi'));
    }
}
