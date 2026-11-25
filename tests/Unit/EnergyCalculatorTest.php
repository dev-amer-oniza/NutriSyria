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
        $this->assertSame(7, EnergyCalculator::trainingDays([['type' => 'walk', 'times' => 14, 'minutes' => 20]]), 'twice a day is still seven days');
    }

    public function test_the_stated_days_count_for_two_rows_or_more_never_below_the_busiest_row(): void
    {
        $two = [['type' => 'walk', 'times' => 2, 'minutes' => 45], ['type' => 'gym', 'times' => 2, 'minutes' => 60]];
        $this->assertSame(4, EnergyCalculator::trainingDays($two, 4), 'two sports on different days');
        $this->assertSame(2, EnergyCalculator::trainingDays($two, 2), 'the same two days');
        $this->assertSame(2, EnergyCalculator::trainingDays($two), 'no answer (older profiles): the busiest row');
        $this->assertSame(2, EnergyCalculator::trainingDays($two, 1), 'never below the busiest row');
        $this->assertSame(7, EnergyCalculator::trainingDays($two, 9), 'never above seven');
        $this->assertSame(3, EnergyCalculator::trainingDays([['type' => 'jog', 'times' => 3, 'minutes' => 30]], 6), 'one row: its own days');

        $this->assertSame(0, EnergyCalculator::fewestDays([]));
        $this->assertSame(3, EnergyCalculator::fewestDays([['type' => 'jog', 'times' => 3, 'minutes' => 30], ['type' => 'brisk_walk', 'times' => 1, 'minutes' => 60]]));
    }

    public function test_the_same_minutes_burn_the_same_however_they_are_written(): void
    {
        // The owner's example, 70 kg, BMR 1647.5: jogging 3 × 30, brisk walking 3 × 30 and 1 × 60.
        $rows = [
            ['type' => 'jog', 'times' => 3, 'minutes' => 30],
            ['type' => 'brisk_walk', 'times' => 3, 'minutes' => 30],
            ['type' => 'brisk_walk', 'times' => 1, 'minutes' => 60],
        ];
        $sitting = 1.4 * 1647.5 / 24;
        $byHand = ((7.5 * 70 - $sitting) * 1.5 + (4.8 * 70 - $sitting) * 2.5) / 7;   // about 178 kcal a day
        $this->assertEqualsWithDelta($byHand, $this->energy()->exercise(1647.5, 70, $rows), 1e-9);
        $this->assertEqualsWithDelta(177.6, $byHand, 0.1);

        // The same week's minutes in one row each give the same calories.
        $merged = [['type' => 'jog', 'times' => 1, 'minutes' => 90], ['type' => 'brisk_walk', 'times' => 1, 'minutes' => 150]];
        $this->assertEqualsWithDelta($byHand, $this->energy()->exercise(1647.5, 70, $merged), 1e-9);
    }
}
