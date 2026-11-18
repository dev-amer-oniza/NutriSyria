<?php

namespace Tests\Unit;

use App\Services\Nutrition\Criteria\EnergyCalculator;
use PHPUnit\Framework\TestCase;

/** The day's factor and the exercise's extra (the owner's decisions, Nov 2026). */
class EnergyCalculatorTest extends TestCase
{
    private function energy(): EnergyCalculator
    {
        return new EnergyCalculator(require __DIR__.'/../../config/nutrition_criteria.php');
    }

    public function test_a_job_on_some_days_counts_the_other_days_as_home(): void
    {
        $this->assertSame(1.40, $this->energy()->factor('home', 5), 'no work days at home');
        $this->assertSame(1.53, $this->energy()->factor('seated', 7));
        $this->assertEqualsWithDelta((3 * 1.53 + 4 * 1.40) / 7, $this->energy()->factor('seated', 3), 1e-9);
        $this->assertEqualsWithDelta((5 * 1.80 + 2 * 1.40) / 7, $this->energy()->factor('physical', 5), 1e-9);
    }

    public function test_the_exercise_takes_off_the_sitting_it_replaces(): void
    {
        // BMR 1647.5, 70 kg: sitting 1.4 × 1647.5 ÷ 24 = 96.1 an hour. Walking 3.0 × 70 = 210: 113.9 extra.
        $walk = $this->energy()->exercise(1647.5, 70, [['type' => 'walk', 'times' => 7, 'minutes' => 60]]);
        $this->assertEqualsWithDelta(210 - 1.4 * 1647.5 / 24, $walk, 1e-9);

        $this->assertSame(0.0, $this->energy()->exercise(1647.5, 70, []));
        $this->assertSame(0.0, $this->energy()->exercise(3000, 40, [['type' => 'walk', 'times' => 2, 'minutes' => 30]]), 'never below nothing');
    }

    public function test_the_whole_day_and_the_pal_check(): void
    {
        $day = $this->energy()->day(1647.5, 70, 'home', null, [['type' => 'walk', 'times' => 2, 'minutes' => 60]]);

        $this->assertEqualsWithDelta(2339.04, $day['tdee'], 0.01);
        $this->assertEqualsWithDelta(1.42, $day['pal'], 0.01);
        $this->assertFalse($day['unusual']);
    }

    public function test_the_hours_a_day_add_every_sport(): void
    {
        $this->assertSame(0.0, (float) EnergyCalculator::dailyHours([]));
        // (3 × 60 + 3 × 15) min ÷ 60 ÷ 7 = 0.536 h
        $this->assertEqualsWithDelta(225 / 60 / 7, EnergyCalculator::dailyHours([['type' => 'weights', 'times' => 3, 'minutes' => 60], ['type' => 'cardio', 'times' => 3, 'minutes' => 15]]), 1e-9);
    }

    public function test_training_days_are_the_most_of_any_one_sport(): void
    {
        $this->assertSame(0, EnergyCalculator::trainingDays([]));
        $this->assertSame(3, EnergyCalculator::trainingDays([['type' => 'weights', 'times' => 3, 'minutes' => 60], ['type' => 'cardio', 'times' => 3, 'minutes' => 15]]));
        $this->assertSame(4, EnergyCalculator::trainingDays([['type' => 'weights', 'times' => 4, 'minutes' => 60], ['type' => 'walk', 'times' => 2, 'minutes' => 30]]));
    }
}
