{{--
    The analysis of a visitor's own version of a kitchen dish ("اطبخها على طريقتك"), returned to
    the page as HTML and never stored. Params: $result (App\Data\Nutrition\CustomRecipeResult),
    $signedIn.
--}}
@php
    $mine = $result->meal;
    $ours = $result->original;
    $number = fn (?float $v) => $v !== null ? \App\Services\Nutrition\Fit\MealFitCalculator::number($v) : '—';
    $compare = [
        'energy_kcal' => ['السعرات', 'سعرة'], 'protein_g' => ['البروتين', 'غ'], 'carbohydrate_g' => ['الكربوهيدرات', 'غ'],
        'fat_g' => ['الدهون', 'غ'], 'fiber_g' => ['الألياف', 'غ'], 'sodium_mg' => ['الصوديوم', 'ملغ'],
    ];
@endphp

<div class="ns-mine">
    <h2 class="t-heading ns-mine__title">نتيجة طبختك</h2>
    @if ($result->unconverted)
        <div class="ns-mine__warn" role="alert">
            <p>لم نستطع حساب وزن هذه المكوّنات، فلم تدخل في الأرقام:</p>
            <ul>
                @foreach ($result->unconverted as $line)
                    <li><strong>{{ $line['label'] }}</strong>@if ($line['problem']): {{ $line['problem'] }}@endif</li>
                @endforeach
            </ul>
        </div>
    @endif

    @include('site.components.meal-values', [
        'meal' => $mine,
        'titleId' => 'mine-values',
        'note' => 'حسب وصفتك: هذا التحليل غير معتمد ولا يُحفظ.',
    ])

    @if ($result->weightEstimated)
        <p class="ns-field__hint">قدّرنا وزن طبختك بعد الطبخ بـ <span class="ns-num">{{ number_format($result->weightAfterCookingG) }}</span> غ بنسبة ما تخسره وصفتنا في الطبخ. إن وزنتها فاكتب الوزن لنتيجة أدق.</p>
    @endif

    <section class="ns-mine__compare" aria-labelledby="mine-compare">
        <h3 class="ns-meal__group-title" id="mine-compare">مقارنة بوصفتنا (للحصة)</h3>
        <table>
            <thead>
                <tr><th scope="col"></th><th scope="col">وصفتك <small>({{ $number($mine->portionGrams()) }} غ)</small></th><th scope="col">وصفتنا <small>({{ $number($ours->portionGrams()) }} غ)</small></th></tr>
            </thead>
            <tbody>
                @foreach ($compare as $code => [$label, $unit])
                    <tr>
                        <th scope="row">{{ $label }}</th>
                        <td><span class="ns-num">{{ $number($mine->amount($code)) }}</span> {{ $mine->amount($code) !== null ? $unit : '' }}</td>
                        <td><span class="ns-num">{{ $number($ours->amount($code)) }}</span> {{ $ours->amount($code) !== null ? $unit : '' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </section>

    <section class="ns-mine__fit" aria-labelledby="mine-fit">
        <h3 class="ns-advice__title" id="mine-fit">مدى مناسبة طبختك لك</h3>
        @if ($result->fit)
            @if ($result->originalLevel !== null && $result->originalLevel !== $result->fit->level)
                <p class="ns-mine__was">بوصفتنا كانت: {{ config('site.fit_levels.'.$result->originalLevel) }}</p>
            @endif
            <div class="ns-fit">
                @include('site.components.fit-result', [
                    'level' => $result->fit->level, 'reasons' => $result->fit->reasons, 'notes' => $result->fit->notes,
                    'share' => $result->fit->share, 'portion' => $result->fit->portion, 'who' => $result->fit->who(),
                    'headline' => $result->fit->headline, 'why' => $result->fit->why,
                    'meal' => $result->meal, 'causes' => $result->fit->causes,
                    'fitSuggestions' => $result->suggestions, 'editor' => true,
                ])
            </div>
        @elseif ($signedIn)
            <p class="t-body">أكمل بياناتك (العمر والطول والوزن والنشاط والهدف) لنقيّم طبختك على جسمك وهدفك.</p>
            <a class="ns-btn ns-btn--primary" href="{{ route('site.criteria') }}">أكمل ملفك</a>
        @else
            <p class="t-body">سجّل الدخول، وأخبرنا عن جسمك وهدفك وحالتك، لنقول لك إن كانت طبختك تناسبك وما الحصة المناسبة لك.</p>
            <div class="ns-book__actions">
                <a class="ns-btn ns-btn--primary" href="{{ route('site.register') }}">أنشئ حسابك</a>
                <a class="ns-btn ns-btn--ghost" href="{{ route('site.login') }}">سجّل الدخول</a>
            </div>
        @endif
    </section>
</div>
