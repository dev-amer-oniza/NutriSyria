<?php

namespace App\Data\Daily;

use App\Models\MealLog;
use Carbon\CarbonImmutable;

/**
 * One day of "يومي": what was eaten, the totals, and what is left of the person's daily
 * needs (their criteria sheet). The four values are energy_kcal, protein_g, carbohydrate_g, fat_g.
 */
final readonly class DayLog
{
    public const VALUES = ['energy_kcal', 'protein_g', 'carbohydrate_g', 'fat_g'];

    /**
     * @param  list<MealLog>  $entries  in the order eaten
     * @param  array<string, float>  $eaten  value => amount counted
     * @param  array<string, float>|null  $needs  value => daily need; null while the profile is incomplete
     * @param  list<array{date: CarbonImmutable, logged: bool, today: bool, future: bool}>  $week  Saturday to Friday
     */
    public function __construct(
        public CarbonImmutable $date,
        public bool $isToday,
        public array $entries,
        public array $eaten,
        public ?array $needs,
        public array $week,
        /** A day of the week before (same weekday), or null before the first week anything was logged. */
        public ?CarbonImmutable $previousWeek = null,
        /** A day of the week after, or null in this week (capped at today). */
        public ?CarbonImmutable $nextWeek = null,
    ) {}

    /** What is left of a daily need (never below zero), or null with no needs. */
    public function left(string $value = 'energy_kcal'): ?float
    {
        return $this->needs === null ? null : max(0.0, ($this->needs[$value] ?? 0) - ($this->eaten[$value] ?? 0));
    }

    /** Eaten past the daily need, if so. */
    public function over(string $value = 'energy_kcal'): float
    {
        return $this->needs === null ? 0.0 : max(0.0, ($this->eaten[$value] ?? 0) - ($this->needs[$value] ?? 0));
    }

    /** Share of the need eaten, 0–100 (capped for the ring). */
    public function percent(string $value = 'energy_kcal'): int
    {
        $need = $this->needs[$value] ?? 0;

        return $need > 0 ? (int) min(100, round(($this->eaten[$value] ?? 0) / $need * 100)) : 0;
    }

    /** Dishes asked for and not counted yet. */
    public function waiting(): int
    {
        return count(array_filter($this->entries, fn (MealLog $e) => ! $e->counted()));
    }
}
