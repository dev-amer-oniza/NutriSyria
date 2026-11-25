<?php

namespace App\Data\Daily;

use Carbon\CarbonImmutable;

/** "وزنك" on the day: the latest weight, how it moved, and whether it is time to weigh again. */
final readonly class WeightCard
{
    /**
     * @param  list<float>  $points  the last weighings, oldest first (for the little line)
     */
    public function __construct(
        /** The latest weighing, else the profile's weight, else null. */
        public ?float $current,
        /** The day of the latest weighing; null before the first one. */
        public ?CarbonImmutable $weighedOn,
        public array $points,
        /** Latest minus the first of the points (negative = lost), with the weeks between. */
        public ?float $change,
        public int $changeWeeks,
        /** No weighing yet, or the last one is a week old or more. */
        public bool $due,
        /** Days since the last weighing (null before the first). */
        public ?int $daysSince,
    ) {}
}
