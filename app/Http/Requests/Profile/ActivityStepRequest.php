<?php

namespace App\Http\Requests\Profile;

use App\Enums\DailyLife;
use App\Enums\Goal;
use App\Services\Nutrition\Criteria\SportAnswers;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * The day without exercise, the exercise, and the goal. The exercise is three questions, all
 * taps (the owner's design): the training days a week (0 = none), the sports, and for each one
 * how many of those days and about how many minutes. Saved as the rows the calculators read
 * (SportAnswers::toRows), so the calculation is unchanged.
 */
class ActivityStepRequest extends ProfileFormRequest
{
    public function rules(): array
    {
        $days = (int) $this->input('training_days');
        $rules = [
            'daily_life' => ['required', Rule::enum(DailyLife::class)],
            // Days a week only for a job or study.
            'work_days' => DailyLife::tryFrom((string) $this->input('daily_life'))?->hasWorkDays()
                ? ['required', 'integer', 'between:1,7'] : ['nullable'],
            'training_days' => ['required', 'integer', 'between:0,7'],
            'sports' => $days > 0
                ? ['required', 'array', 'max:'.(int) config('nutrition_criteria.max_exercises')] : ['prohibited'],
            'sports.*' => ['distinct', Rule::in(array_keys(config('nutrition_criteria.exercises')))],
            'goal' => ['required', Rule::enum(Goal::class)],
        ];

        foreach ($this->chosen() as $type) {
            $rules["sport_days.{$type}"] = ['required', 'integer', 'between:1,'.max(1, $days)];
            $rules["sport_minutes.{$type}"] = ['required', 'integer', Rule::in(SportAnswers::minuteOptions())];
        }

        return $rules;
    }

    protected function prepareForValidation(): void
    {
        $days = filled($this->input('training_days')) ? self::number($this->input('training_days')) : null;
        // No training days: whatever else was ticked is not sport.
        $sports = (int) $days > 0 ? array_values(array_filter((array) $this->input('sports', []), 'is_string')) : [];

        $this->merge([
            'work_days' => filled($this->input('work_days')) ? self::number($this->input('work_days')) : null,
            'training_days' => $days,
            'sports' => $sports,
            'sport_days' => array_map(fn ($v) => self::number($v), array_intersect_key((array) $this->input('sport_days', []), array_flip($sports))),
            'sport_minutes' => array_map(fn ($v) => self::number($v), array_intersect_key((array) $this->input('sport_minutes', []), array_flip($sports))),
        ]);
    }

    /** Each training day has a sport: the sports' days together reach the days in all. */
    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $days = (int) $this->input('training_days');
            $spread = array_sum(array_map('intval', (array) $this->input('sport_days')));
            if ($days > 0 && $spread < $days) {
                $validator->errors()->add('training_days',
                    "قلت إنك بتتمرّن {$days} أيام، ووزّعت رياضاتك على {$spread} بس. زيد أيام رياضة، أو قلّل أيام التمرين.");
            }
        }];
    }

    /** @return list<string> the sports ticked, known ones only */
    private function chosen(): array
    {
        $known = config('nutrition_criteria.exercises');

        return array_values(array_unique(array_filter((array) $this->input('sports', []), fn ($t) => is_string($t) && isset($known[$t]))));
    }

    /**
     * What UserProfileService::saveActivity keeps: the rows and the days in all.
     *
     * @return array{daily_life: string, work_days: ?int, goal: string, exercises: list<array{type: string, times: int, minutes: int}>, training_days: ?int}
     */
    public function activity(): array
    {
        $data = $this->validated();
        $sports = [];
        foreach ($data['sports'] ?? [] as $type) {
            $sports[$type] = ['days' => (int) $data['sport_days'][$type], 'minutes' => (int) $data['sport_minutes'][$type]];
        }

        return [
            'daily_life' => $data['daily_life'],
            'work_days' => isset($data['work_days']) ? (int) $data['work_days'] : null,
            'goal' => $data['goal'],
            'exercises' => SportAnswers::toRows($sports),
            'training_days' => (int) $data['training_days'] ?: null,
        ];
    }

    public function messages(): array
    {
        return [
            'training_days.required' => 'قلّنا كم يوم بالأسبوع بتتمرّن، أو اختار «ولا يوم».',
            'sports.required' => 'اختار شو بتعمل بأيام تمرينك.',
            'sports.max' => 'اختار لحد :max رياضات، الأكتر شي بتعملها.',
            'sport_days.*.required' => 'قلّنا كم يوم بتعمل كل رياضة اخترتها.',
            'sport_days.*.between' => 'أيام كل رياضة من 1 لعدد أيام تمرينك (:max).',
            'sport_minutes.*.required' => 'قلّنا كم دقيقة تقريباً لكل رياضة اخترتها.',
        ];
    }

    public function attributes(): array
    {
        return [
            'daily_life' => 'يومك', 'work_days' => 'أيام الدوام', 'goal' => 'الهدف',
            'training_days' => 'أيام التمرين بالأسبوع', 'sports' => 'الرياضة', 'sports.*' => 'الرياضة',
            'sport_days.*' => 'أيام الرياضة', 'sport_minutes.*' => 'دقائق الرياضة',
        ];
    }
}
