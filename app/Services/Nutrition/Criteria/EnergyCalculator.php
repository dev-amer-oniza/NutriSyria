<?php

namespace App\Services\Nutrition\Criteria;

/**
 * The day's energy (the owner's decisions, Nov 2026). Pure: numbers in, numbers out.
 *
 *   TDEE = BMR × the day's factor (without exercise) + the exercise's extra
 *
 * The day's factor (FAO/WHO/UNU 2004 Table 5.1, config daily_life) is averaged over the week
 * when work is on some days only: the days off count as home. The exercise's extra an hour is
 * MET × weight (1 MET = 1 kcal/kg/hr) less what that hour already counted as sitting
 * (replaced_par × BMR ÷ 24), so nothing is counted twice; it is averaged over the week.
 */
final class EnergyCalculator
{
    /** @param  array<string, mixed>  $rules  config('nutrition_criteria') */
    public function __construct(private readonly array $rules) {}

    /**
     * @param  list<array{type: string, times: int, minutes: int}>  $exercises
     * @return array{factor: float, base: float, exercise: float, tdee: float, pal: float, unusual: bool}
     */
    public function day(float $bmr, float $weightKg, string $dailyLife, ?int $workDays, array $exercises): array
    {
        $factor = $this->factor($dailyLife, $workDays);
        $base = $bmr * $factor;
        $exercise = $this->exercise($bmr, $weightKg, $exercises);
        $tdee = $base + $exercise;
        $pal = $bmr > 0 ? $tdee / $bmr : 0.0;
        [$low, $high] = $this->rules['pal_range'];

        return [
            'factor' => $factor,
            'base' => $base,
            'exercise' => $exercise,
            'tdee' => $tdee,
            'pal' => $pal,
            // Outside what adults sustain: the answers are worth checking.
            'unusual' => round($pal, 2) < $low || round($pal, 2) > $high,
        ];
    }

    /** The day's factor without exercise, averaged over the week when work is on some days only. */
    public function factor(string $dailyLife, ?int $workDays): float
    {
        $factors = $this->rules['daily_life'];
        $home = (float) $factors['home'];
        $factor = (float) $factors[$dailyLife];
        if ($dailyLife === 'home' || $workDays === null) {
            return $factor;
        }
        $days = max(0, min(7, $workDays));

        return ($days * $factor + (7 - $days) * $home) / 7;
    }

    /**
     * The exercise's extra a day: for each sport, (MET × weight − the sitting it replaces) an
     * hour, times its hours a week, over seven days. Never below nothing.
     *
     * @param  list<array{type: string, times: int, minutes: int}>  $exercises
     */
    public function exercise(float $bmr, float $weightKg, array $exercises): float
    {
        $sitting = (float) $this->rules['replaced_par'] * $bmr / 24;
        $total = 0.0;
        foreach ($exercises as $one) {
            $met = (float) ($this->rules['exercises'][$one['type']]['met'] ?? 0);
            $hours = max(0, (int) $one['times']) * max(0, (int) $one['minutes']) / 60;
            $total += max(0.0, $met * $weightKg - $sitting) * $hours / 7;
        }

        return $total;
    }

    /**
     * The days a week the person trains: the most of any one sport (two sports on the same
     * day are one training day; we cannot tell, so we never count more than we know).
     *
     * @param  list<array{type: string, times: int, minutes: int}>  $exercises
     */
    public static function trainingDays(array $exercises): int
    {
        return $exercises === [] ? 0 : max(array_map(fn (array $e) => (int) $e['times'], $exercises));
    }

    /**
     * The training hours a day on average: every sport's hours a week, over seven days.
     *
     * @param  list<array{type: string, times: int, minutes: int}>  $exercises
     */
    public static function dailyHours(array $exercises): float
    {
        return array_sum(array_map(fn (array $e) => max(0, (int) $e['times']) * max(0, (int) $e['minutes']), $exercises)) / 60 / 7;
    }
}
