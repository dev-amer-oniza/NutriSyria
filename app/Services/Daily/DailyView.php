<?php

namespace App\Services\Daily;

use App\Data\Daily\DayLog;
use App\Data\Nutrition\MealFacts;
use App\Enums\MealType;
use App\Models\User;
use App\Services\Nutrition\Fit\MealFitService;
use App\Services\Site\MealCatalog;
use Carbon\CarbonImmutable;

/**
 * Everything the "يومي" page shows: the day, and for today the meal of this hour with the
 * dishes that suit it (decision: in the morning breakfast, in the afternoon lunch, at night
 * dinner). Meals come from the shared catalog cache; only the person's verdicts are computed.
 */
class DailyView
{
    public function __construct(
        private readonly DailyLog $log,
        private readonly MealCatalog $catalog,
        private readonly MealFitService $fits,
        private readonly MealPeriods $periods,
    ) {}

    /** @return array{day: DayLog, type: MealType, suggestions: list<array<string, mixed>>} */
    public function for(User $user, ?CarbonImmutable $date = null): array
    {
        $day = $this->log->day($user, $date);
        $type = MealType::forHour(DailyLog::now()->hour);

        $suggestions = [];
        if ($day->isToday && $day->needs !== null) {
            $meals = $this->catalog->meals();
            $verdicts = $this->fits->judgeAll($user, $meals);
            $picked = DailySuggestions::pick($meals, $verdicts, fn (MealFacts $m) => $this->periods->of($m),
                $type, $day->left() ?? 0, (int) config('site.daily.suggestions'));
            $suggestions = array_map(fn (MealFacts $m) => $m->card($verdicts[$m->id] ?? null), $picked);
        }

        return ['day' => $day, 'type' => $type, 'suggestions' => $suggestions];
    }
}
