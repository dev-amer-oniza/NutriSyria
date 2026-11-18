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

    @if (! empty($template['sport']))
        @php $sport = $template['sport']; @endphp
        <p class="ns-template__sport">
            تمرينك <b>{{ $sport['label'] }}</b> (نحو <span class="ns-num">{{ (int) round($sport['hours'] * 60) }}</span> دقيقة في اليوم كمعدّل)، ومن يتمرّن هكذا يحتاج
            <span class="ns-num">{{ $sport['range'][0] }}–{{ $sport['range'][1] }}</span> غ كربوهيدرات لكل كغ من وزنه. حصتك <span class="ns-num">{{ $sport['per_kg'] }}</span> غ لكل كغ.
            @if ($sport['short'])
                <br>سعراتك لهدفك لا تكفي أكثر من ذلك، فخفّفنا الدهون إلى أقل حد. هذا يكفي للأيام الهادئة، أما لتمرين قوي فاستشر أخصائياً ليوزّع أكلك عليه.
            @endif
        </p>
    @endif
</section>
