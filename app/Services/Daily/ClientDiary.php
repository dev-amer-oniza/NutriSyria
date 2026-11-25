<?php

namespace App\Services\Daily;

use App\Data\Daily\DayLog;
use App\Data\Daily\Diary;
use App\Models\MealLog;
use App\Models\User;
use App\Models\WeightLog;

/**
 * A client's "يومي" for their nutritionist's file (ViewClient): the last DAYS days and the last
 * weighings, in two queries. The panel page checks the link (ClientResource: an accepted link
 * of this nutritionist only), so this only reads.
 */
class ClientDiary
{
    public const DAYS = 14;

    public const WEIGHTS = 8;

    public function for(User $client): Diary
    {
        $today = DailyLog::now()->startOfDay();
        $from = $today->subDays(self::DAYS - 1);

        $byDay = MealLog::query()->whereBelongsTo($client)
            ->whereDate('eaten_on', '>=', $from->toDateString())
            ->orderBy('eaten_at')->orderBy('id')
            ->get()
            ->groupBy(fn (MealLog $e) => $e->eaten_on->toDateString());

        $days = [];
        for ($day = $today; $day->greaterThanOrEqualTo($from); $day = $day->subDay()) {
            $entries = $byDay->get($day->toDateString())?->all() ?? [];
            $eaten = array_fill_keys(DayLog::VALUES, 0.0);
            foreach ($entries as $entry) {
                foreach (DayLog::VALUES as $value) {
                    $eaten[$value] += (float) ($entry->{$value} ?? 0);
                }
            }
            $days[] = [
                'date' => $day,
                'entries' => array_values($entries),
                'eaten' => $eaten,
                'waiting' => count(array_filter($entries, fn (MealLog $e) => ! $e->counted())),
            ];
        }

        $weights = WeightLog::query()->whereBelongsTo($client)
            ->orderByDesc('weighed_on')->limit(self::WEIGHTS)->get()->all();

        return new Diary($days, $weights);
    }
}
