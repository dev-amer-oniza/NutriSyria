<?php

namespace Tests\Unit;

use App\Enums\Goal;
use App\Services\Nutrition\Criteria\BmiCalculator;
use PHPUnit\Framework\TestCase;

/** The owner's BMI classes (WHO), the healthy range, and the goal warnings (decisions 1a 2a 3a). */
class BmiCalculatorTest extends TestCase
{
    private function calculator(): BmiCalculator
    {
        return new BmiCalculator((require __DIR__.'/../../config/nutrition_criteria.php')['bmi']);
    }

    /** A weight giving exactly this index for 1.70 m. */
    private function at(float $bmi, ?Goal $goal = null, array $conditions = []): \App\Data\Nutrition\BmiAssessment
    {
        return $this->calculator()->assess(170, $bmi * 1.7 ** 2, $goal, $conditions);
    }

    public function test_each_class_starts_where_the_owner_set_it(): void
    {
        $cases = [
            [18.4, 'underweight'], [18.5, 'normal'], [24.9, 'normal'], [25.0, 'overweight'], [29.9, 'overweight'],
            [30.0, 'obese_1'], [34.9, 'obese_1'], [35.0, 'obese_2'], [39.9, 'obese_2'], [40.0, 'obese_3'], [52.0, 'obese_3'],
        ];
        foreach ($cases as [$bmi, $class]) {
            $this->assertSame($class, $this->at($bmi)->class, "BMI {$bmi}");
        }
    }

    public function test_the_person_sees_a_gentle_label_and_the_nutritionist_the_clinical_one(): void
    {
        $result = $this->at(36);

        $this->assertSame('أعلى بكثير من الطبيعي (الدرجة الثانية)', $result->label);
        $this->assertSame('سمنة درجة ثانية (متوسطة)', $result->clinical);
    }

    public function test_the_value_and_healthy_range_for_a_height(): void
    {
        $result = $this->calculator()->assess(178, 80);

        $this->assertSame(25.2, $result->value);
        $this->assertSame([59, 78], [$result->healthyMinKg, $result->healthyMaxKg]); // 18.5 and 24.9 × 1.78²
    }

    public function test_a_goal_against_the_class_is_warned_about_never_blocked(): void
    {
        $this->assertStringContainsString('لا ننصحك بإنقاص الوزن', $this->at(17.5, Goal::Lose)->warning);
        $this->assertStringContainsString('قد لا تناسبك زيادة الوزن', $this->at(31, Goal::Gain)->warning);
        $this->assertNull($this->at(17.5, Goal::Gain)->warning);
        $this->assertNull($this->at(27, Goal::Gain)->warning);       // overweight: no warning
        $this->assertNull($this->at(22, Goal::Lose)->warning);
    }

    public function test_pregnancy_keeps_the_number_but_not_the_class(): void
    {
        $result = $this->at(31, Goal::Gain, ['pregnant_or_breastfeeding']);

        $this->assertSame(31.0, $result->value);
        $this->assertFalse($result->classified());
        $this->assertNull($result->warning);
    }

    public function test_no_index_without_height_or_weight(): void
    {
        $this->assertNull($this->calculator()->assess(0, 70));
    }
}
