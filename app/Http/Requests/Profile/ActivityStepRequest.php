<?php

namespace App\Http\Requests\Profile;

use App\Enums\DailyLife;
use App\Enums\Goal;
use Illuminate\Validation\Rule;

/**
 * The day without exercise, the exercise, and the goal. The exercise rows come from a small
 * repeater: an empty row (no type) is dropped before validation, so "no sport" is no rows.
 */
class ActivityStepRequest extends ProfileFormRequest
{
    public function rules(): array
    {
        return [
            'daily_life' => ['required', Rule::enum(DailyLife::class)],
            // Days a week only for a job or study.
            'work_days' => DailyLife::tryFrom((string) $this->input('daily_life'))?->hasWorkDays()
                ? ['required', 'integer', 'between:1,7'] : ['nullable'],
            'exercises' => ['array', 'max:'.(int) config('nutrition_criteria.max_exercises')],
            'exercises.*.type' => ['required', Rule::in(array_keys(config('nutrition_criteria.exercises')))],
            'exercises.*.times' => ['required', 'integer', 'between:1,14'],
            'exercises.*.minutes' => ['required', 'integer', 'between:5,300'],
            'goal' => ['required', Rule::enum(Goal::class)],
        ];
    }

    protected function prepareForValidation(): void
    {
        $rows = array_values(array_filter((array) $this->input('exercises', []), fn ($row) => is_array($row) && filled($row['type'] ?? null)));
        $this->merge([
            'exercises' => array_map(fn (array $row) => [
                'type' => $row['type'],
                'times' => self::number($row['times'] ?? ''),
                'minutes' => self::number($row['minutes'] ?? ''),
            ], $rows),
            'work_days' => filled($this->input('work_days')) ? self::number($this->input('work_days')) : null,
        ]);
    }

    public function attributes(): array
    {
        return [
            'daily_life' => 'يومك', 'work_days' => 'أيام الدوام', 'goal' => 'الهدف', 'exercises' => 'الرياضة',
            'exercises.*.type' => 'نوع الرياضة', 'exercises.*.times' => 'عدد المرات بالأسبوع', 'exercises.*.minutes' => 'مدة المرة بالدقائق',
        ];
    }
}
