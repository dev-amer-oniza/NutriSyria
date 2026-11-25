@extends('site.layouts.onboarding')

@section('title', 'نشاطي وهدفي')
@section('heading', 'نشاطك وهدفك')
@section('lead', 'سؤالان منفصلان: كيف يمضي نهارك بلا رياضة، ثم الرياضة إن وُجدت. اختر الأقرب إلى أسبوعك المعتاد، لا إلى أفضل أسابيعك.')

@section('form')
    @php
        // The saved rows as the question's answers (rows of one sport from before are joined),
        // or what was just sent when the form comes back with an error.
        $saved = \App\Services\Nutrition\Criteria\SportAnswers::fromRows($profile->exercises, $profile->training_days);
        $sent = session()->hasOldInput();
        $chosen = $sent ? array_values((array) old('sports', [])) : array_keys($saved['sports']);
        $sports = [];
        foreach ($options['exercises'] as $key => $exercise) {
            $sports[$key] = [
                'days' => (int) ($sent ? old("sport_days.$key") : ($saved['sports'][$key]['days'] ?? 0)),
                'minutes' => (int) ($sent ? old("sport_minutes.$key") : ($saved['sports'][$key]['minutes'] ?? 0)),
            ];
        }
        $state = [
            'day' => old('daily_life', $profile->daily_life?->value),
            'days' => $sent ? (old('training_days') === null || old('training_days') === '' ? null : (int) old('training_days')) : ($profile->daily_life ? $saved['days'] : null),
            'chosen' => $chosen,
            'sports' => $sports,
            'perHour' => $options['perHour'],
            'max' => (int) config('nutrition_criteria.max_exercises'),
            'more' => collect($chosen)->contains(fn ($k) => empty($options['exercises'][$k]['popular'])),
        ];
        $workDays = (int) old('work_days', $profile->work_days ?? 0);
        $popular = array_filter($options['exercises'], fn ($e) => ! empty($e['popular']));
        $others = array_filter($options['exercises'], fn ($e) => empty($e['popular']));
        $minutes = \App\Services\Nutrition\Criteria\SportAnswers::minuteOptions();
    @endphp
    <form class="ns-form" method="post" action="{{ route('site.onboarding.activity') }}" novalidate x-data="activityStep(@js($state))">
        @csrf
        @include('site.components.form.choice-cards', [
            'name' => 'daily_life', 'legend' => '1. نهارك بلا رياضة', 'cols' => 2, 'model' => 'day',
            'hint' => 'شو بتعمل أغلب نهارك؟ الرياضة بنسألك عنها بعدين.',
            'selected' => $profile->daily_life?->value,
            'options' => collect($options['days'])->mapWithKeys(fn ($d) => [$d->value => ['label' => $d->label(), 'hint' => $d->examples()]])->all(),
        ])

        <fieldset class="ns-field" x-show="day && day !== 'home'" x-cloak>
            <legend class="ns-field__label">كم يوم بالأسبوع عندك دوام أو دراسة؟</legend>
            <p class="ns-field__hint">باقي الأيام نحسبها كأنك بالبيت.</p>
            <div class="ns-days">
                @foreach (range(1, 7) as $n)
                    <label class="ns-chip ns-days__day">
                        <input class="ns-type__radio" type="radio" name="work_days" value="{{ $n }}" @checked($workDays === $n)>
                        <span class="ns-num">{{ $n }}</span>
                    </label>
                @endforeach
            </div>
            @error('work_days')<p class="ns-field__error">{{ $message }}</p>@enderror
        </fieldset>

        <fieldset class="ns-field ns-train">
            <legend class="ns-field__label">2. الرياضة والتمرين</legend>
            <p class="ns-field__hint">تقريباً بيكفي، على أسبوعك العادي.</p>

            <p class="ns-train__q" id="train-days">كم يوم بالأسبوع بتتمرّن؟</p>
            <div class="ns-days" role="radiogroup" aria-labelledby="train-days">
                @foreach (range(0, 7) as $n)
                    <label @class(['ns-chip', 'ns-days__day', 'ns-days__none' => $n === 0])>
                        <input class="ns-type__radio" type="radio" name="training_days" value="{{ $n }}" x-model.number="days">
                        @if ($n === 0)<span>ولا يوم</span>@else<span class="ns-num">{{ $n }}</span>@endif
                    </label>
                @endforeach
            </div>
            @error('training_days')<p class="ns-field__error">{{ $message }}</p>@enderror

            <div class="ns-train__more" x-show="days > 0" x-cloak>
                <p class="ns-train__q" id="train-what">شو بتعمل بهالأيام؟</p>
                <p class="ns-field__hint">اختار وحدة أو أكتر، لحد {{ config('nutrition_criteria.max_exercises') }}.</p>
                <div class="ns-sports" role="group" aria-labelledby="train-what">
                    @foreach ([$popular, $others] as $group => $list)
                        @foreach ($list as $key => $exercise)
                            <label class="ns-sportcard" @if ($group === 1) x-show="more || chosen.includes('{{ $key }}')" x-cloak @endif
                                   :class="{ 'is-off': ! chosen.includes('{{ $key }}') && chosen.length >= max }">
                                <input class="ns-type__radio" type="checkbox" name="sports[]" value="{{ $key }}" x-model="chosen"
                                       :disabled="! chosen.includes('{{ $key }}') && chosen.length >= max">
                                <span class="ns-sportcard__icon">@include('site.partials.icon', ['name' => $exercise['icon'] ?? 'cardio'])</span>
                                <span class="ns-sportcard__name">{{ $exercise['label'] }}</span>
                            </label>
                        @endforeach
                    @endforeach
                </div>
                <button class="ns-btn ns-btn--secondary ns-btn--sm ns-train__others" type="button" x-show="! more" @click="more = true">رياضات تانية</button>
                @error('sports')<p class="ns-field__error">{{ $message }}</p>@enderror

                <template x-for="key in chosen" :key="key">
                    <div class="ns-sportq">
                        <p class="ns-sportq__title" x-text="label(key)"></p>
                        <p class="ns-sportq__ask">من أيام تمرينك الـ<span class="ns-num" x-text="days"></span>، كم يوم؟</p>
                        <div class="ns-days">
                            <template x-for="n in days" :key="n">
                                <label class="ns-chip ns-days__day">
                                    <input class="ns-type__radio" type="radio" :name="`sport_days[${key}]`" :value="n" x-model.number="sports[key].days">
                                    <span class="ns-num" x-text="n"></span>
                                </label>
                            </template>
                        </div>
                        <p class="ns-sportq__ask">كم دقيقة تقريباً؟</p>
                        <div class="ns-days">
                            @foreach ($minutes as $m)
                                <label class="ns-chip ns-days__day ns-days__min">
                                    <input class="ns-type__radio" type="radio" :name="`sport_minutes[${key}]`" value="{{ $m }}" x-model.number="sports[key].minutes">
                                    <span class="ns-num">{{ $m }}{{ $loop->last ? '+' : '' }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                </template>
                @foreach ($errors->get('sport_days.*') + $errors->get('sport_minutes.*') as $messages)
                    <p class="ns-field__error">{{ $messages[0] }}</p>
                @endforeach

                <p class="ns-train__sum" x-show="extra() > 0" x-cloak>
                    رياضتك بتزيد احتياجك حوالي <b class="ns-num" x-text="extra()"></b> سعرة باليوم.
                </p>
            </div>
        </fieldset>

        @include('site.components.form.choice-cards', [
            'name' => 'goal', 'legend' => '3. هدفك', 'selected' => $profile->goal?->value,
            'options' => collect($options['goals'])->mapWithKeys(fn ($g) => [$g->value => ['label' => $g->label()]])->all(),
        ])
        <div class="ns-form__row">
            <a class="ns-btn ns-btn--ghost" href="{{ route('site.onboarding.show', \App\Enums\OnboardingStep::Body) }}">السابق</a>
            <button class="ns-btn ns-btn--primary" type="submit">التالي</button>
        </div>
    </form>
@endsection
