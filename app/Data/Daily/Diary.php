<?php

namespace App\Data\Daily;

use App\Models\MealLog;
use App\Models\WeightLog;
use Carbon\CarbonImmutable;

/**
 * A client's "يومي" as their nutritionist reads it (ClientDiary): the last days, newest first,
 * with what was eaten each day, and the last weighings. Read only.
 */
final readonly class Diary
{
    /**
     * @param  list<array{date: CarbonImmutable, entries: list<MealLog>, eaten: array<string, float>, waiting: int}>  $days  newest first
     * @param  list<WeightLog>  $weights  newest first
     */
    public function __construct(
        public array $days,
        public array $weights,
    ) {}

    /** Days with at least one meal counted. */
    public function loggedDays(): int
    {
        return count(array_filter($this->days, fn (array $d) => $d['eaten']['energy_kcal'] > 0));
    }

    /** The mean of a value over the logged days only (an empty day says nothing). */
    public function average(string $value = 'energy_kcal'): ?float
    {
        $logged = array_filter($this->days, fn (array $d) => $d['eaten']['energy_kcal'] > 0);

        return $logged ? array_sum(array_map(fn (array $d) => $d['eaten'][$value], $logged)) / count($logged) : null;
    }

    /** Latest minus oldest of the weighings shown (negative = lost), or null with fewer than two. */
    public function weightChange(): ?float
    {
        return count($this->weights) > 1 ? round($this->weights[0]->weight_kg - $this->weights[count($this->weights) - 1]->weight_kg, 1) : null;
    }
}
