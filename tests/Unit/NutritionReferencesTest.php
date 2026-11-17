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
            ...$this->rules['activity_refs'], ...$this->rules['goal_refs'], ...$this->rules['min_kcal_refs'],
            ...$this->rules['bmi']['refs'], ...$this->rules['templates']['balanced']['refs'],
            ...$this->rules['templates']['celiac']['allergen_refs'],
        ];
        foreach ($this->rules['criteria'] as $criterion) {
            $named = [...$named, ...($criterion['refs'] ?? [])];
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

    public function test_the_balanced_template_is_inside_the_reference_ranges(): void
    {
        $macros = $this->rules['templates']['balanced']['macros'];

        $this->assertStringContainsString('Adults: Fat 20–35 · Carbohydrate 45–65 · Protein 10–35', $this->quote('nasem_amdr'));
        $this->assertTrue($macros['carbs'] >= 45 && $macros['carbs'] <= 65);
        $this->assertTrue($macros['protein'] >= 10 && $macros['protein'] <= 35);
        $this->assertTrue($macros['fat'] >= 20 && $macros['fat'] <= 35);
        $this->assertStringContainsString('limit total fat intake to 30% of total energy intake or less', $this->quote('who_fats_2023'));
        $this->assertLessThanOrEqual(30, $macros['fat']);
        $this->assertStringNotContainsString('ADA', $this->rules['templates']['balanced']['label']);
    }

    public function test_the_energy_equation_is_mifflins(): void
    {
        $this->assertStringContainsString('REE (males) = 10 x weight (kg) + 6.25 x height (cm) - 5 x age (y) + 5', $this->quote('mifflin_1990'));
        $this->assertStringContainsString('REE (females) = 10 x weight (kg) + 6.25 x height (cm) - 5 x age (y) - 161', $this->quote('mifflin_1990'));

        $this->assertSame(10 * 80 + 6.25 * 178 - 5 * 30 + 5, EnergyEquation::Mifflin->bmr(Sex::Male, 30, 178, 80));
        $this->assertSame(10 * 60 + 6.25 * 160 - 5 * 45 - 161, EnergyEquation::Mifflin->bmr(Sex::Female, 45, 160, 60));
    }

    public function test_every_activity_factor_is_inside_the_fao_ranges(): void
    {
        $quote = $this->quote('fao_energy_2004');
        $this->assertStringContainsString('Sedentary or light activity lifestyle 1.40-1.69', $quote);
        $this->assertStringContainsString('Active or moderately active lifestyle 1.70-1.99', $quote);
        $this->assertStringContainsString('Vigorous or vigorously active lifestyle 2.00-2.40', $quote);

        $ranges = ['sedentary' => [1.40, 1.69], 'light' => [1.40, 1.69], 'moderate' => [1.70, 1.99], 'very' => [2.00, 2.40], 'extreme' => [2.00, 2.40]];
        foreach ($this->rules['activity_factors'] as $level => $factor) {
            [$low, $high] = $ranges[$level];
            $this->assertTrue($factor >= $low && $factor <= $high, "$level $factor");
        }
    }

    public function test_the_deficit_and_the_floor_follow_the_obesity_guideline(): void
    {
        $quote = $this->quote('aha_obesity_2013');

        $this->assertSame(-500, $this->rules['goal_adjustment']['lose']);
        $this->assertStringContainsString('Prescribe a 500-kcal/d or 750-kcal/d energy deficit', $quote);
        $this->assertStringContainsString('for obese or overweight individuals', $quote);
        $this->assertSame(25, $this->rules['deficit_min_bmi']);
        $this->assertStringContainsString('overweight is a BMI greater than or equal to 25', $this->quote('who_bmi'));

        $this->assertSame(['female' => 1200, 'male' => 1500], $this->rules['min_kcal']);
        $this->assertStringContainsString('1200–1500 kcal/d for women and 1500–1800 kcal/d for men', $quote);

        $this->assertFalse($this->rules['surplus_judged'], 'no guideline for +300');
    }

    public function test_the_body_mass_index_classes_read_from_who(): void
    {
        $below = array_column($this->rules['bmi']['classes'], 'below', 'key');

        $this->assertSame([18.5, 25, 30], [$below['underweight'], $below['normal'], $below['overweight']]);
        $this->assertStringContainsString('BMI < 18.5', $this->quote('who_bmi'));
        $this->assertStringContainsString('obesity is a BMI greater than or equal to 30', $this->quote('who_bmi'));
    }

    public function test_celiac_disease_avoids_gluten(): void
    {
        $this->assertContains('gluten', $this->rules['templates']['celiac']['allergens']);
        $this->assertStringContainsString('follow a gluten-free diet', $this->quote('niddk_celiac'));
    }
}
