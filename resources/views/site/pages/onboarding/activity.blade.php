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
            <p class="ns-field__hint">كل رياضة بنوعها، وكم مرة بالأسبوع، وكم دقيقة المرة. ما بتتمرّن؟ اتركها فاضية.</p>
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
                        <span class="ns-sport__label">مرات بالأسبوع</span>
                        <input class="ns-input" type="text" inputmode="numeric" :name="`exercises[${i}][times]`" x-model="row.times">
                    </label>
                    <label>
                        <span class="ns-sport__label">دقائق المرة</span>
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
