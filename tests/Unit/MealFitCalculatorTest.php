<?php

namespace Tests\Unit;

use App\Data\Nutrition\CriteriaSheet;
use App\Data\Nutrition\CriterionValue;
use App\Data\Nutrition\MealFacts;
use App\Enums\CriterionSource;
use App\Services\Nutrition\Criteria\CriteriaCalculator;
use App\Services\Nutrition\Fit\MealFitCalculator;
use PHPUnit\Framework\TestCase;

/**
 * The design's example man (lightly active ×1.55: 2240 kcal, balanced template). One meal = 30% of the day:
 * 672 kcal, 84 g carbohydrate, 33.6 g protein, 22.5 g fat, 7.5 g saturated, 16.8 g sugars,
 * 9.3 g fibre, 600 mg sodium.
 */
class MealFitCalculatorTest extends TestCase
{
    private array $rules;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rules = require __DIR__.'/../../config/nutrition_criteria.php';
    }

    private function sheet(array $overrides = []): CriteriaSheet
    {
        return (new CriteriaCalculator($this->rules))->calculate($overrides + [
            'sex' => 'male', 'age' => 30, 'height_cm' => 178, 'weight_kg' => 80,
            'activity' => 'light', 'goal' => 'lose', 'conditions' => [], 'allergens' => [],
        ]);
    }

    /** A 350 g serving that fits the example man exactly, changed by $nutrients. */
    private function meal(array $nutrients = [], array $allergens = [], bool $checked = true, ?float $serving = 350): MealFacts
    {
        return new MealFacts(
            id: 1, name: 'فتة', brand: 'مطعم', description: null, servingGrams: $serving,
            nutrients: $nutrients + [
                'energy_kcal' => 600, 'carbohydrate_g' => 80, 'protein_g' => 30, 'fat_g' => 20,
                'fat_sat_g' => 5, 'sugars_g' => 10, 'fiber_g' => 10, 'sodium_mg' => 500,
            ],
            verification: 'calculated', image: null, allergens: $allergens, allergensChecked: $checked,
        );
    }

    private function judge(MealFacts $meal, ?CriteriaSheet $sheet = null)
    {
        return (new MealFitCalculator($this->rules))->judge($sheet ?? $this->sheet(), $meal);
    }

    public function test_a_meal_within_every_limit_suits_very_well(): void
    {
        $fit = $this->judge($this->meal());

        $this->assertSame(4, $fit->level);
        $this->assertSame(['grams' => 350, 'kcal' => 600, 'whole' => true], $fit->portion);
        $this->assertSame(['energy' => 27, 'protein' => 27, 'carbs' => 29, 'fat' => 27], $fit->share);
        $this->assertStringContainsString('27% من احتياجك اليومي', $fit->reasons[0]);
        $this->assertSame([], $fit->notes);
        $this->assertSame('المتوازن', $fit->template);
    }

    public function test_the_level_follows_the_largest_overshoot(): void
    {
        // Sodium 720 mg = 20% over its 600 mg meal share.
        $fit = $this->judge($this->meal(['sodium_mg' => 720]));
        $this->assertSame(3, $fit->level);
        $this->assertSame('الصوديوم فيها 720 ملغ، وحصة وجبة من يومك 600 ملغ (من 2,000 ملغ في اليوم)، أي أكثر بـ20%.', $fit->reasons[0]);

        // Fat 31.5 g = 40% over 22.5 g.
        $this->assertSame(2, $this->judge($this->meal(['fat_g' => 31.5]))->level);

        // Energy 1075 kcal = 60% over 672: a target, so "needs attention" at most (step 2).
        $this->assertSame(2, $this->judge($this->meal(['energy_kcal' => 1075]))->level);

        // Exactly 10% over still suits very well.
        $this->assertSame(4, $this->judge($this->meal(['sodium_mg' => 660]))->level);
    }

    public function test_the_suitable_portion_keeps_every_limit(): void
    {
        // Fat 31.5 g over 22.5 g: 71% of 350 g = 250 g (down to tens), 600 × 250/350 = 429 kcal.
        $fit = $this->judge($this->meal(['fat_g' => 31.5]));
        $this->assertSame(['grams' => 250, 'kcal' => 429, 'whole' => false], $fit->portion);

        // Less than 40% of the serving: no portion is offered.
        $this->assertNull($this->judge($this->meal(['sodium_mg' => 2000]))->portion);
    }

    public function test_reasons_are_ordered_by_overshoot_and_capped_at_three(): void
    {
        $fit = $this->judge($this->meal(['sodium_mg' => 690, 'fat_g' => 30, 'fat_sat_g' => 9, 'energy_kcal' => 740]));

        $this->assertCount(3, $fit->reasons);
        $this->assertStringStartsWith('الدهون فيها', $fit->reasons[0]);      // 33% over
        $this->assertStringStartsWith('الدهون المشبعة', $fit->reasons[1]);  // 20% over
    }

    public function test_total_sugars_are_shown_but_never_judge_a_meal(): void
    {
        // 30 g against a 16.8 g share would be 79% over: but the reference is for free sugars,
        // and ours include fruit and milk sugar (decision 11a).
        $fit = $this->judge($this->meal(['sugars_g' => 30]));

        $this->assertSame(4, $fit->level);
        $this->assertStringNotContainsString('السكريات', implode(' ', [...$fit->reasons, ...$fit->notes]));
    }

    public function test_protein_is_short_against_its_rda_not_the_target(): void
    {
        // The target's meal share is 33.6 g, the RDA's 16.8 g (56 g a day for a man).
        $this->assertSame([], $this->judge($this->meal(['protein_g' => 20]))->notes, '20 g is under the target, not under the need');

        $short = $this->judge($this->meal(['protein_g' => 10]));
        $this->assertSame(4, $short->level);
        $this->assertSame(['البروتين فيها 10 غ، أقل من حصة وجبة من الحد الأدنى لاحتياجك (17 غ). كمّلها في وجبة أخرى من يومك.'], $short->notes);
    }

    public function test_being_under_a_minimum_or_protein_is_only_a_note(): void
    {
        $fit = $this->judge($this->meal(['fiber_g' => 2, 'protein_g' => 60]));

        $this->assertSame(4, $fit->level); // more protein than the target does not count
        $this->assertCount(1, $fit->notes);
        $this->assertStringStartsWith('الألياف فيها 2 غ، أقل من حصة وجبة', $fit->notes[0]);
    }

    public function test_a_missing_value_is_left_out_and_named(): void
    {
        $meal = new MealFacts(1, 'فتة', 'مطعم', null, 350,
            ['energy_kcal' => 600, 'carbohydrate_g' => 80, 'protein_g' => 30, 'fat_g' => 20], 'calculated', null, [], true);

        $fit = $this->judge($meal);

        $this->assertSame(4, $fit->level);
        $this->assertSame(['لا تتوفر قيمة الدهون المشبعة والصوديوم لهذه الوجبة، فلم تدخل في التقييم.'], $fit->notes);
    }

    public function test_an_allergen_makes_the_meal_unfit_with_no_portion(): void
    {
        $fit = $this->judge($this->meal([], ['gluten', 'sesame']), $this->sheet(['allergens' => ['gluten']]));

        $this->assertSame(1, $fit->level);
        $this->assertNull($fit->portion);
        $this->assertSame('فيها الغلوتين، وكتبتَ في ملفك أنه يسبّب لك حساسية.', $fit->reasons[0]);
    }

    public function test_celiac_disease_avoids_gluten_through_its_template(): void
    {
        $fit = $this->judge($this->meal([], ['gluten']), $this->sheet(['conditions' => ['celiac']]));

        $this->assertSame(1, $fit->level);
        $this->assertSame('فيها الغلوتين، ومرض السيلياك يُعالَج بتجنّبه تماماً.', $fit->reasons[0]);
        $this->assertSame('قيّمناها على: احتياجك العام (موثّق)، وتجنّب الغلوتين.', $fit->grounds);
        $this->assertFalse($fit->general, 'celiac brings no rule waiting for its references');
    }

    public function test_unchecked_ingredients_warn_people_with_allergies_only(): void
    {
        $withAllergy = $this->judge($this->meal([], [], checked: false), $this->sheet(['allergens' => ['peanuts']]));
        $this->assertSame(4, $withAllergy->level);
        $this->assertStringStartsWith('لم نتحقق بعد من مسبّبات الحساسية', $withAllergy->reasons[0]);

        $without = $this->judge($this->meal([], [], checked: false));
        $this->assertStringNotContainsString('مسبّبات الحساسية', implode(' ', $without->reasons));
    }

    /** A doctor's number for one criterion, as the person types it in معاييري. */
    private function withDoctor(CriteriaSheet $sheet, string $key, float $value): CriteriaSheet
    {
        $definition = $this->rules['criteria'][$key];
        $base = $sheet->get($key) ?? new CriterionValue($key, $definition['label'], $definition['unit'], $definition['bound'],
            $definition['scope'], $definition['nutrient'], $value, CriterionSource::Default, '');

        return $sheet->with($base->overriddenBy(CriterionSource::Doctor, $value, null));
    }

    public function test_a_rule_not_verified_yet_is_shown_but_never_judges(): void
    {
        // The diabetes template's 60 g a meal is waiting for its references: 70 g changes nothing.
        $fit = $this->judge($this->meal(['carbohydrate_g' => 70]), $this->sheet(['conditions' => ['diabetes']]));

        $this->assertSame(4, $fit->level);
        $this->assertTrue($fit->general);
        $this->assertSame(['للمعلومة، لا يدخل في التقييم بعد: كربوهيدرات الوجبة فيها 70 غ، وحد «قالب السكري (تحكّم بالكربوهيدرات)» 60 غ للوجبة قيد التوثيق. إن حدّد لك طبيبك هذا الحد، اكتبه في معاييري فنقيّم عليه.'], $fit->info);
        $this->assertSame('قيّمناها على: احتياجك العام (موثّق). قيد التوثيق: حدود السكري. ننصحك بأخصائي.', $fit->grounds);
        $this->assertStringContainsString('وكل الحدود الموثّقة محترمة', $fit->reasons[0]);
    }

    public function test_a_doctors_number_judges_as_a_strict_limit(): void
    {
        $sheet = $this->withDoctor($this->sheet(['conditions' => ['diabetes']]), 'meal_carbohydrate_g', 60);

        // 70 g is 17% over: in moderation, held to the doctor's number.
        $fit = $this->judge($this->meal(['carbohydrate_g' => 70]), $sheet);
        $this->assertSame(3, $fit->level);
        $this->assertSame('كربوهيدرات الوجبة فيها 70 غ، والحد الذي حدّده لك طبيبك 60 غ للوجبة، أي أكثر بـ17%.', $fit->reasons[0]);
        $this->assertSame([], $fit->info);
        $this->assertStringContainsString('وأرقام طبيبك', $fit->grounds);

        // 100 g is 67% over: a doctor's limit may say "not for you now".
        $this->assertSame(1, $this->judge($this->meal(['carbohydrate_g' => 100]), $sheet)->level);
    }

    public function test_one_reason_a_nutrient_the_one_that_drops_the_verdict_most(): void
    {
        // Fat 31.5 g: 40% over the day's share (22.5 g, a target) and 110% over the doctor's 15 g a meal.
        $sheet = $this->withDoctor($this->sheet(), 'meal_fat_g', 15);
        $fit = $this->judge($this->meal(['fat_g' => 31.5]), $sheet);

        $this->assertSame(1, $fit->level);
        $this->assertCount(1, array_filter($fit->reasons, fn (string $r) => str_contains($r, 'دهون')));
        $this->assertStringStartsWith('دهون الوجبة فيها 32 غ، والحد الذي حدّده لك طبيبك 15 غ للوجبة', $fit->reasons[0]);
    }

    public function test_values_per_100_g_when_the_serving_is_unknown(): void
    {
        $fit = $this->judge($this->meal(['fat_g' => 31.5], serving: null));

        $this->assertSame(['grams' => 70, 'kcal' => 420, 'whole' => false], $fit->portion);
    }

    /** The nutritionist's plan lunch: 35% of 2240 = 784 kcal, with carbs kept to 60 g on purpose. */
    private function withLunch(): CriteriaSheet
    {
        return $this->sheet()->withMeal([
            'name' => 'غداء', 'time' => '14:00', 'share' => 0.35, 'kcal' => 784,
            'targets' => ['energy_kcal' => 784, 'carbohydrate_g' => 60, 'protein_g' => 39, 'fat_g' => 26],
        ]);
    }

    public function test_with_a_plan_the_dish_is_compared_with_the_lunch_the_nutritionist_set(): void
    {
        // 80 g carbs: fine for 30% of the day (84 g), a third over the plan lunch's 60 g.
        $fit = $this->judge($this->meal(), $this->withLunch());

        $this->assertSame(2, $fit->level);
        $this->assertSame('الكربوهيدرات فيها 80 غ، وغداؤك في خطتك 60 غ، أي أكثر بـ33%.', $fit->reasons[0]);
        $this->assertSame('غدائك في خطة أخصائيّك (14:00، 784 سعرة)', $fit->basis);
        $this->assertStringContainsString('مقارنةً بغدائك في خطة أخصائيّك', $fit->who());
    }

    public function test_the_other_limits_take_the_lunch_share_of_the_day(): void
    {
        // Sodium: 35% of 2000 = 700 mg, so 720 mg is just 3% over (it was 20% over 600).
        $sheet = $this->withLunch();
        $this->assertEqualsWithDelta(700.0, $sheet->perMeal($sheet->get('sodium_mg')), 0.001);
        $this->assertSame(60.0, $sheet->perMeal($sheet->get('carbohydrate_g')));

        $fit = $this->judge($this->meal(['carbohydrate_g' => 60, 'sodium_mg' => 720]), $sheet);
        $this->assertSame(4, $fit->level);
        $this->assertSame('الصوديوم فيها 720 ملغ، أي بحدود غدائك في خطتك (700 ملغ).', $fit->reasons[0]);

        $within = $this->judge($this->meal(['carbohydrate_g' => 60]), $sheet);
        $this->assertStringContainsString('ضمن غدائك في خطتك', $within->reasons[0]);
    }

    public function test_just_over_a_limit_reads_within_not_a_tiny_percent(): void
    {
        // 15.1 g of fat against the doctor's meal limit of 15 g.
        $sheet = $this->withDoctor($this->sheet(), 'meal_fat_g', 15);
        $fit = $this->judge($this->meal(['fat_g' => 15.1, 'energy_kcal' => 450]), $sheet);

        $this->assertSame(4, $fit->level);
        $this->assertContains('دهون الوجبة فيها 15 غ، أي بحدود الحد الذي حدّده لك طبيبك للوجبة (15 غ).', $fit->reasons);
    }

    public function test_legume_fibre_gets_a_note_for_a_sensitive_digestion_only(): void
    {
        $hummus = new MealFacts(
            id: 2, name: 'حمص بطحينة', brand: 'المطبخ السوري', description: null, servingGrams: 115,
            nutrients: ['energy_kcal' => 268, 'carbohydrate_g' => 26, 'protein_g' => 10, 'fat_g' => 15, 'fiber_g' => 7.6, 'sodium_mg' => 306],
            verification: 'calculated', image: null, allergensChecked: true, ingredients: ['حمص', 'طحينة', 'ثوم'],
        );

        $sensitive = $this->judge($hummus, $this->sheet(['conditions' => ['gallbladder_removed']]));
        $this->assertStringContainsString('7.6 غ ألياف من البقوليات', implode(' ', $sensitive->notes));

        // Without such a condition, or without legumes, no note.
        $this->assertStringNotContainsString('البقوليات', implode(' ', $this->judge($hummus)->notes));
        $noLegumes = new MealFacts(
            id: 3, name: 'بطاطا', brand: 'x', description: null, servingGrams: 115,
            nutrients: ['fiber_g' => 7.6], verification: 'calculated', image: null, ingredients: ['بطاطا'],
        );
        $this->assertStringNotContainsString('البقوليات', implode(' ', $this->judge($noLegumes, $this->sheet(['conditions' => ['gallbladder_removed']]))->notes));
    }
}
