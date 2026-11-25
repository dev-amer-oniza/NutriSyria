<?php

namespace App\Services\Daily;

use App\Data\Daily\DayLog;
use App\Data\Nutrition\MealFacts;
use App\Enums\MealType;
use App\Models\MealLog;
use App\Models\User;
use App\Services\Nutrition\Fit\MealFitService;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * "يومي": what a person ate, day by day. A meal is logged with the values of the part eaten,
 * kept as they were (a later recipe change never rewrites the past). Reading a day costs two
 * queries whatever is in it: the day's entries and the week's logged dates.
 */
class DailyLog
{
    public function __construct(private readonly MealFitService $fits) {}

    /** "Now" on the person's clock (config site.daily.timezone). */
    public static function now(): CarbonImmutable
    {
        return CarbonImmutable::now(config('site.daily.timezone'));
    }

    /** @return list<float> the amounts one may log of this meal */
    public static function amountsFor(MealFacts $meal): array
    {
        return config($meal->servingGrams === null ? 'site.daily.per_100g' : 'site.daily.plates');
    }

    /** "نص الصحن", "200 غ": an amount in words. */
    public static function amountLabel(float $amount, bool $per100g): string
    {
        if ($per100g) {
            return (int) round($amount * 100).' غ';
        }

        return match (true) {
            $amount >= 2.0 => 'صحنين',
            $amount >= 1.5 => 'صحن ونص',
            $amount >= 1.0 => 'الصحن كامل',
            $amount >= 0.75 => 'تلت أرباع الصحن',
            $amount >= 0.5 => 'نص الصحن',
            default => 'ربع الصحن',
        };
    }

    public function log(User $user, MealFacts $meal, float $amount, MealType $type): MealLog
    {
        if (! in_array($amount, self::amountsFor($meal), true)) {
            throw new InvalidArgumentException('Not an amount one may log of this meal.');
        }

        $now = self::now();
        $values = [];
        foreach (DayLog::VALUES as $value) {
            $values[$value] = isset($meal->nutrients[$value]) ? round($meal->nutrients[$value] * $amount, 1) : 0.0;
        }

        $log = new MealLog;
        $log->forceFill([
            'user_id' => $user->getKey(),
            'eaten_on' => $now->toDateString(),
            'eaten_at' => now(),
            'meal_type' => $type,
            'product_id' => $meal->id,
            'name' => $meal->name,
            'amount' => $amount,
            'per_100g' => $meal->servingGrams === null,
            ...$values,
        ])->save();

        return $log;
    }

    /** Takes one of the person's own entries off their day (someone else's is "not found"). */
    public function remove(User $user, int $id): void
    {
        MealLog::query()->whereBelongsTo($user)->findOrFail($id)->delete();
    }

    public function day(User $user, ?CarbonImmutable $date = null): DayLog
    {
        $today = self::now()->startOfDay();
        $date = ($date ?? $today)->startOfDay();
        if ($date->greaterThan($today)) {
            $date = $today;
        }

        $entries = MealLog::query()->whereBelongsTo($user)
            ->whereDate('eaten_on', $date->toDateString())
            ->orderBy('eaten_at')->orderBy('id')
            ->get()->all();

        $eaten = array_fill_keys(DayLog::VALUES, 0.0);
        foreach ($entries as $entry) {
            foreach (DayLog::VALUES as $value) {
                $eaten[$value] += (float) ($entry->{$value} ?? 0);
            }
        }

        return new DayLog($date, $date->equalTo($today), $entries, $eaten, $this->needs($user), $this->week($user, $date, $today));
    }

    /** @return array<string, float>|null the four daily needs from the person's criteria sheet */
    private function needs(User $user): ?array
    {
        $sheet = $this->fits->sheetFor($user);
        if (! $sheet) {
            return null;
        }

        $needs = [];
        foreach (DayLog::VALUES as $value) {
            $needs[$value] = (float) ($sheet->get($value)->value ?? 0);
        }

        return $needs;
    }

    /** @return list<array{date: CarbonImmutable, logged: bool, today: bool, future: bool}> */
    private function week(User $user, CarbonImmutable $date, CarbonImmutable $today): array
    {
        // The week starts on Saturday, as in Syria.
        $start = $date->subDays(($date->dayOfWeek + 1) % 7);
        $end = $start->addDays(6);

        $logged = MealLog::query()->whereBelongsTo($user)
            ->whereDate('eaten_on', '>=', $start->toDateString())
            ->whereDate('eaten_on', '<=', $end->toDateString())
            ->distinct()->pluck('eaten_on')
            ->map(fn ($d) => CarbonImmutable::parse($d)->toDateString())
            ->flip();

        $days = [];
        for ($day = $start; $day->lessThanOrEqualTo($end); $day = $day->addDay()) {
            $days[] = [
                'date' => $day,
                'logged' => $logged->has($day->toDateString()),
                'today' => $day->equalTo($today),
                'future' => $day->greaterThan($today),
            ];
        }

        return $days;
    }
}
