@extends('site.layouts.onboarding')

@section('title', 'نشاطي وهدفي')
@section('heading', 'نشاطك وهدفك')
@section('lead', 'سؤالان منفصلان: كيف يمضي نهارك بلا رياضة، ثم الرياضة إن وُجدت. اختر الأقرب إلى أسبوعك المعتاد، لا إلى أفضل أسابيعك.')

@section('form')
    @php
        $rows = old('exercises', $profile->exercises ?? []);
        $state = [
            'day' => old('daily_life', $profile->daily_life?->value),
            'rows' => array_values(array_map(fn ($r) => ['type' => $r['type'] ?? '', 'times' => $r['times'] ?? '', 'minutes' => $r['minutes'] ?? ''], is_array($rows) ? $rows : [])),
            'max' => (int) config('nutrition_criteria.max_exercises'),
        ];
        $workDays = (int) old('work_days', $profile->work_days ?? 0);
        $trainingDays = (int) old('training_days', $profile->training_days ?? 0);
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

        <fieldset class="ns-field">
            <legend class="ns-field__label">2. الرياضة والتمرين</legend>
            <p class="ns-field__hint">اكتب الرياضة اللي بتعملها بأسبوعك العادي. ما بتتمرّن؟ اتركها فاضية.</p>
            <div class="ns-sport-tip">
                <p class="ns-sport-tip__title">بتعمل أكتر من رياضة، أو المدة بتختلف من يوم ليوم؟</p>
                <ul class="ns-sport-tip__list">
                    <li>كل رياضة بسطر لحالها، حتى لو بنفس اليوم.</li>
                    <li>نفس الرياضة بمدة مختلفة؟ ضيفها بسطر تاني.</li>
                </ul>
                <details class="ns-sport-example">
                    <summary class="ns-sport-example__btn">
                        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 18h6M10 21h4"/><path d="M12 3a6 6 0 0 0-3.6 10.8c.6.5 1 1.2 1 2V16h5.2v-.2c0-.8.4-1.5 1-2A6 6 0 0 0 12 3Z"/></svg>
                        شوف مثال
                    </summary>
                    <div class="ns-sport-example__body">
                        <p class="ns-sport-example__story">«بتمرّن 3 أيام بالأسبوع ساعة: نص ساعة هرولة ونص ساعة مشي سريع. وبيوم رابع بمشي مشي سريع ساعة كاملة.»</p>
                        <p class="ns-sport-example__how">هيك بتعبّي الخانات:</p>
                        <table class="ns-sport-example__table">
                            <thead><tr><th scope="col">النوع</th><th scope="col">كم يوم بالأسبوع؟</th><th scope="col">كم دقيقة باليوم؟</th></tr></thead>
                            <tbody>
                                <tr><td>هرولة</td><td><span class="ns-num">3</span></td><td><span class="ns-num">30</span></td></tr>
                                <tr><td>مشي سريع</td><td><span class="ns-num">3</span></td><td><span class="ns-num">30</span></td></tr>
                                <tr><td>مشي سريع</td><td><span class="ns-num">1</span></td><td><span class="ns-num">60</span></td></tr>
                            </tbody>
                        </table>
                        <p class="ns-sport-example__how">وبسؤال «بالمجمل، كم يوم بالأسبوع بتتمرّن؟» بتختار <strong class="ns-num">4</strong>.</p>
                    </div>
                </details>
            </div>
            <template x-for="(row, i) in rows" :key="i">
                <div class="ns-sport">
                    <label class="ns-sport__type">
                        <span class="ns-sport__label">النوع</span>
                        <select class="ns-input" :name="`exercises[${i}][type]`" x-model="row.type">
                            <option value="">اختر</option>
                            @foreach ($options['exercises'] as $key => $exercise)
                                <option value="{{ $key }}">{{ $exercise['label'] }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label>
                        <span class="ns-sport__label">كم يوم بالأسبوع؟</span>
                        <input class="ns-input" type="text" inputmode="numeric" :name="`exercises[${i}][times]`" x-model="row.times">
                    </label>
                    <label>
                        <span class="ns-sport__label">كم دقيقة باليوم؟</span>
                        <input class="ns-input" type="text" inputmode="numeric" :name="`exercises[${i}][minutes]`" x-model="row.minutes">
                    </label>
                    <button class="ns-sport__remove" type="button" @click="remove(i)" aria-label="احذف هذه الرياضة">×</button>
                </div>
            </template>
            <button class="ns-btn ns-btn--ghost ns-sport__add" type="button" @click="add()" x-show="rows.length < max"
                    x-text="rows.length ? 'أضف رياضة تانية' : 'أضف رياضة'">أضف رياضة</button>
            @foreach (['exercises', 'exercises.*.type', 'exercises.*.times', 'exercises.*.minutes'] as $field)
                @error($field)<p class="ns-field__error">{{ $message }}</p>@enderror
            @endforeach

            {{-- Two rows or more: they cannot tell whether two sports share a day, so we ask. --}}
            <div class="ns-sport-days" x-show="rows.length > 1" x-cloak>
                <p class="ns-field__label" id="training-days-label">بالمجمل، كم يوم بالأسبوع بتتمرّن؟</p>
                <p class="ns-field__hint">اليوم اللي بتعمل فيه رياضتين بينحسب يوم واحد.</p>
                <div class="ns-days" role="radiogroup" aria-labelledby="training-days-label">
                    @foreach (range(1, 7) as $n)
                        <label class="ns-chip ns-days__day">
                            <input class="ns-type__radio" type="radio" name="training_days" value="{{ $n }}" @checked($trainingDays === $n)>
                            <span class="ns-num">{{ $n }}</span>
                        </label>
                    @endforeach
                </div>
                @error('training_days')<p class="ns-field__error">{{ $message }}</p>@enderror
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
