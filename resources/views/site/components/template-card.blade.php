{{--
    The person's diet template and how it shares the day's calories, read only: the template
    comes from the calculation, or from their nutritionist. Params: $sheet (CriteriaSheet).
--}}
@php
    $template = $sheet->template;
    $parts = ['carbs' => ['الكربوهيدرات', 'carbohydrate_g', 'ns-key-carbs'], 'protein' => ['البروتين', 'protein_g', 'ns-key-protein'], 'fat' => ['الدهون', 'fat_g', 'ns-key-fat']];
@endphp
<section class="ns-template" aria-labelledby="template-title">
    <div class="ns-template__head">
        <div>
            <p class="ns-template__kicker">قالبك الغذائي</p>
            <h2 class="ns-template__name" id="template-title">{{ $template['label'] }}</h2>
            <p class="ns-template__why">
                @if ($template['source'] === \App\Enums\CriterionSource::Nutritionist)
                    <span class="ns-source ns-source--nutritionist">اختاره أخصائيّك</span>
                @endif
                {{ $template['description'] }}
            </p>
            @if (! empty($template['note']))
                <p class="ns-field__hint">ملاحظة أخصائيّك: {{ $template['note'] }}</p>
            @endif
        </div>
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
</section>
