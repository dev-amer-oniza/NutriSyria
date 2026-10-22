{{--
    The person's diet template: name, why it was chosen, and how it shares the day's calories.
    Params: $sheet (CriteriaSheet); on "my criteria" also $selectable (templates a person may
    pick) and $locked (their nutritionist chose it).
--}}
@php
    $template = $sheet->template;
    $parts = ['carbs' => ['الكربوهيدرات', 'carbohydrate_g', 'ns-key-carbs'], 'protein' => ['البروتين', 'protein_g', 'ns-key-protein'], 'fat' => ['الدهون', 'fat_g', 'ns-key-fat']];
@endphp
<section class="ns-template" aria-labelledby="template-title" x-data="{ choosing: false }">
    <div class="ns-template__head">
        <div>
            <p class="ns-template__kicker">قالبك الغذائي</p>
            <h2 class="ns-template__name" id="template-title">{{ $template['label'] }}</h2>
            <p class="ns-template__why">
                <span @class(['ns-source', 'ns-source--'.$template['source']->value])>
                    {{ match ($template['source']) {
                        \App\Enums\CriterionSource::Default => 'اخترناه حسب حالتك',
                        \App\Enums\CriterionSource::User => 'اخترته أنت',
                        \App\Enums\CriterionSource::Nutritionist => 'اختاره أخصائيّك',
                    } }}
                </span>
                {{ $template['description'] }}
            </p>
            @if (! empty($template['note']))
                <p class="ns-field__hint">ملاحظة أخصائيّك: {{ $template['note'] }}</p>
            @endif
        </div>
        @isset($selectable)
            @if ($locked)
                <span class="ns-criterion__locked">يغيّره أخصائيّك فقط</span>
            @else
                <button class="ns-btn ns-btn--ghost ns-btn--sm" type="button" x-show="! choosing" @click="choosing = true">تغيير القالب</button>
            @endif
        @endisset
    </div>

    <div class="ns-macrobar" role="img" aria-label="توزيع السعرات حسب القالب">
        @foreach ($parts as $macro => [$label, $key, $class])
            <span class="{{ $class }}" style="flex-grow: {{ $template['macros'][$macro] }}"></span>
        @endforeach
    </div>
    <dl class="ns-template__macros">
        @foreach ($parts as $macro => [$label, $key, $class])
            @php $c = $sheet->get($key); @endphp
            <div>
                <dt><i class="ns-stat__key {{ $class }}"></i>{{ $label }} <span class="ns-num">{{ $template['macros'][$macro] }}%</span></dt>
                <dd><span class="ns-num">{{ number_format($c->value) }}</span> غ في اليوم</dd>
            </div>
        @endforeach
    </dl>

    @isset($selectable)
        @unless ($locked)
            <form class="ns-template__choose" method="post" action="{{ route('site.template.update') }}" x-show="choosing" x-cloak>
                @csrf @method('put')
                @include('site.components.form.choice-cards', [
                    'name' => 'template', 'legend' => 'اختر قالباً عاماً', 'cols' => 1, 'selected' => $template['key'],
                    // Words, not "50 / 20 / 30": numbers joined by slashes reverse in right-to-left text.
                    'options' => collect($selectable)->map(fn ($t) => [
                        'label' => $t['label'],
                        'hint' => 'كربوهيدرات '.$t['macros']['carbs'].'% · بروتين '.$t['macros']['protein'].'% · دهون '.$t['macros']['fat'].'%. '.$t['description'],
                    ])->all(),
                ])
                <p class="ns-field__hint">قوالب الحالات الصحية (السكري، الكلى...) تُختار تلقائياً من حالتك، أو يختارها لك أخصائي.</p>
                <div class="ns-form__row">
                    <button class="ns-btn ns-btn--ghost ns-btn--sm" type="button" @click="choosing = false">إلغاء</button>
                    <button class="ns-btn ns-btn--primary ns-btn--sm" type="submit">اعتمد القالب</button>
                </div>
            </form>
            @if ($template['source'] === \App\Enums\CriterionSource::User)
                <form method="post" action="{{ route('site.template.reset') }}" x-show="! choosing">
                    @csrf @method('delete')
                    <button class="ns-link ns-resend__btn" type="submit">أرجع القالب المناسب لحالتي</button>
                </form>
            @endif
        @endunless
    @endisset
</section>
