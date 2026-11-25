<?php

namespace App\Services\Nutrition\Criteria;

/**
 * The sport question as the person answers it (the owner's design, point 1): the training days
 * a week, the sports, and for each sport how many of those days and about how many minutes.
 * The profile keeps the same rows the calculators read (type, times, minutes) plus the days in
 * all, so nothing in the calculation changes (pure; tests/Unit/SportAnswersTest.php).
 */
final class SportAnswers
{
    /** @return list<int> the minutes a session offered (the last reads "90+") */
    public static function minuteOptions(): array
    {
        return array_map('intval', config('nutrition_criteria.exercise_minutes'));
    }

    /** The offered minutes nearest to an average (a tie takes the longer). */
    public static function nearestMinutes(float $minutes): int
    {
        $best = null;
        foreach (self::minuteOptions() as $option) {
            if ($best === null || abs($option - $minutes) <= abs($best - $minutes)) {
                $best = $option;
            }
        }

        return (int) $best;
    }

    /**
     * Stored rows to the question's answers: one card per sport (rows of the same sport from
     * before are joined: their days added up to seven, the minutes their weighted mean rounded to
     * an offered value), and the days in all (never fewer than the busiest sport).
     *
     * @param  list<array<string, mixed>>|null  $rows  as stored (rows from before may lack a field)
     * @return array{days: int, sports: array<string, array{days: int, minutes: int}>}
     */
    public static function fromRows(?array $rows, ?int $stated = null): array
    {
        $groups = [];
        foreach ($rows ?? [] as $row) {
            $type = (string) ($row['type'] ?? '');
            $times = max(0, (int) ($row['times'] ?? 0));
            if ($type === '' || $times === 0) {
                continue;
            }
            $groups[$type]['times'] = ($groups[$type]['times'] ?? 0) + $times;
            $groups[$type]['total'] = ($groups[$type]['total'] ?? 0) + $times * max(0, (int) ($row['minutes'] ?? 0));
        }

        $sports = [];
        foreach ($groups as $type => $g) {
            $sports[$type] = ['days' => min(7, $g['times']), 'minutes' => self::nearestMinutes($g['total'] / $g['times'])];
        }

        $busiest = $sports ? max(array_column($sports, 'days')) : 0;

        return ['days' => $sports ? min(7, max($busiest, (int) $stated)) : 0, 'sports' => $sports];
    }

    /**
     * The answers back to stored rows, in the order chosen.
     *
     * @param  array<string, array{days: int, minutes: int}>  $sports
     * @return list<array{type: string, times: int, minutes: int}>
     */
    public static function toRows(array $sports): array
    {
        $rows = [];
        foreach ($sports as $type => $answer) {
            $rows[] = ['type' => (string) $type, 'times' => (int) $answer['days'], 'minutes' => (int) $answer['minutes']];
        }

        return $rows;
    }

    /**
     * The extra a day for each sport, an hour of it, for the live line "رياضتك بتزيد احتياجك…":
     * MET × weight − the sitting it replaces (the same rule as EnergyCalculator::exercise).
     *
     * @return array<string, float> sport => kcal an hour
     */
    public static function extraPerHour(float $bmr, float $weightKg): array
    {
        $sitting = (float) config('nutrition_criteria.replaced_par') * $bmr / 24;

        return array_map(fn (array $e) => round(max(0.0, (float) $e['met'] * $weightKg - $sitting), 1), config('nutrition_criteria.exercises'));
    }
}
