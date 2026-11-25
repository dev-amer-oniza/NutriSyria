<?php

namespace Tests\Unit;

use App\Services\Nutrition\Criteria\SportAnswers;
use Tests\TestCase;

class SportAnswersTest extends TestCase
{
    public function test_rows_of_one_sport_join_with_their_days_and_mean_minutes(): void
    {
        $answers = SportAnswers::fromRows([
            ['type' => 'jog', 'times' => 3, 'minutes' => 30],
            ['type' => 'brisk_walk', 'times' => 3, 'minutes' => 30],
            ['type' => 'brisk_walk', 'times' => 1, 'minutes' => 60],
        ], 4);

        $this->assertSame(['days' => 4, 'sports' => [
            'jog' => ['days' => 3, 'minutes' => 30],
            'brisk_walk' => ['days' => 4, 'minutes' => 45], // (90 + 60) ÷ 4 = 37.5: a tie takes the longer
        ]], $answers);
    }

    public function test_the_days_in_all_are_never_fewer_than_the_busiest_sport_nor_more_than_seven(): void
    {
        $this->assertSame(5, SportAnswers::fromRows([['type' => 'walk', 'times' => 5, 'minutes' => 30]], 2)['days']);
        $this->assertSame(7, SportAnswers::fromRows([['type' => 'walk', 'times' => 10, 'minutes' => 30]])['sports']['walk']['days']);
        $this->assertSame(['days' => 0, 'sports' => []], SportAnswers::fromRows(null, 4));
    }

    public function test_minutes_go_to_the_nearest_offered(): void
    {
        $this->assertSame(15, SportAnswers::nearestMinutes(5));
        $this->assertSame(30, SportAnswers::nearestMinutes(35));
        $this->assertSame(90, SportAnswers::nearestMinutes(150));
    }

    public function test_answers_go_back_to_the_rows_the_calculators_read(): void
    {
        $this->assertSame(
            [['type' => 'weights', 'times' => 3, 'minutes' => 60], ['type' => 'cardio', 'times' => 3, 'minutes' => 15]],
            SportAnswers::toRows(['weights' => ['days' => 3, 'minutes' => 60], 'cardio' => ['days' => 3, 'minutes' => 15]]),
        );
    }

    public function test_the_extra_an_hour_takes_off_the_sitting_it_replaces(): void
    {
        // Amer: BMR 1648, 70 kg. Walking (3.0 MET): 210 − 1.4 × 1648 ÷ 24 = 113.9.
        $this->assertSame(113.9, SportAnswers::extraPerHour(1648, 70)['walk']);
    }
}
