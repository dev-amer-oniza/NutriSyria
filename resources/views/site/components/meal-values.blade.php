{{--
    One meal's values for a serving: energy, the macro bar, the four main figures, then the
    groups of config('site.meal_values'). Params: $meal (App\Data\Nutrition\MealFacts),
    optional $titleId and $note (a line under the heading, e.g. "حسب وصفتك").
--}}
@php
    $titleId ??= 'values-title';
    $value = fn (string $code) => $meal->amount($code) !== null ? \App\Services\Nutrition\Fit\MealFitCalculator::number($meal->amount($code)) : '—';
    $grams = fn (string $code) => (int) round($meal->amount($code) ?? 0);
    // Each group, with the values it shows for this meal (always ones, or known ones).
    $groups = collect(config('site.meal_values'))
        ->map(fn (array $group) => $group + ['shown' => array_filter($group['values'], fn (array $v, string $code) => $v[2] || $meal->amount($code) !== null, ARRAY_FILTER_USE_BOTH)])
        ->filter(fn (array $group) => $group['shown'] !== []);
@endphp
<section class="ns-meal__values" aria-labelledby="{{ $titleId }}" x-data="{ src: null }">
    <h2 class="t-heading" id="{{ $titleId }}">
        @if ($meal->servingGrams)
            القيم لحصة <span class="ns-num">{{ round($meal->servingGrams) }}</span> غ
        @else
            القيم لكل <span class="ns-num">100</span> غ <small class="ns-muted">(لا نعرف وزن الحصة بعد)</small>
        @endif
    </h2>
    @isset($note)
        <p class="ns-meal__mine-note">{{ $note }}</p>
    @endisset
    @if ($meal->withBread)
        @php $loaf = app(\App\Services\Site\BreadReference::class)->loaf(); @endphp
        <div class="ns-bread" role="note">
            <span class="ns-bread__icon">@include('site.partials.icon', ['name' => 'bread'])</span>
            <div>
                <p class="ns-bread__title">تؤكل عادةً مع الخبز، والأرقام هنا للصحن وحده</p>
                @if ($loaf)
                    <p class="ns-bread__text">كل رغيف خبز عربي (نحو <span class="ns-num">{{ $loaf['grams'] }}</span> غ) يضيف تقريباً <strong><span class="ns-num">{{ $loaf['kcal'] }}</span> سعرة</strong> و<strong><span class="ns-num">{{ $loaf['carbs'] }}</span> غ كربوهيدرات</strong>.</p>
                @else
                    <p class="ns-bread__text">الخبز والمرافقات تزيد السعرات والكربوهيدرات، فاحسبها فوق هذه الأرقام.</p>
                @endif
            </div>
        </div>
    @endif
    <div class="ns-energy__fig">
        <span class="ns-num t-figure-xl" data-count>{{ $value('energy_kcal') }}</span>
        <span class="ns-energy__unit">سعرة حرارية</span>
    </div>
    @include('site.components.macro-bar', ['protein' => $grams('protein_g'), 'carbs' => $grams('carbohydrate_g'), 'fat' => $grams('fat_g')])
    <div class="ns-panel__stats">
        @include('site.components.nutrient-stat', ['label' => 'بروتين', 'value' => $value('protein_g'), 'key' => 'protein'])
        @include('site.components.nutrient-stat', ['label' => 'كربوهيدرات', 'value' => $value('carbohydrate_g'), 'key' => 'carbs'])
        @include('site.components.nutrient-stat', ['label' => 'دهون', 'value' => $value('fat_g'), 'key' => 'fat'])
        @include('site.components.nutrient-stat', ['label' => 'ألياف', 'value' => $value('fiber_g'), 'key' => 'fiber'])
    </div>
    @php
        $main = array_filter(['protein_g' => 'البروتين', 'carbohydrate_g' => 'الكربوهيدرات', 'fat_g' => 'الدهون', 'fiber_g' => 'الألياف'], fn (string $label, string $code) => $meal->sourcesOf($code) !== [], ARRAY_FILTER_USE_BOTH);
    @endphp
    @if ($main)
        {{-- "من أين؟" (step 4): the ingredients behind each value, one open at a time. --}}
        <div class="ns-src-chips">
            @foreach ($main as $code => $label)
                <button type="button" class="ns-chip ns-chip--sm" @click="src = src === '{{ $code }}' ? null : '{{ $code }}'" :aria-expanded="src === '{{ $code }}'">من أين {{ $label }}؟</button>
            @endforeach
        </div>
        @foreach ($main as $code => $label)
            <div x-show="src === '{{ $code }}'" x-cloak>@include('site.components.meal-sources', ['code' => $code])</div>
        @endforeach
    @endif
    @foreach ($groups as $group)
        @if ($group['folded'])
            <details class="ns-meal__group">
                <summary class="ns-meal__group-title">عرض {{ $group['title'] }}</summary>
        @else
            <div class="ns-meal__group">
                <h3 class="ns-meal__group-title">{{ $group['title'] }}</h3>
        @endif
                <dl class="ns-meal__more">
                    @foreach ($group['shown'] as $code => [$label, $unit])
                        <div><dt>{{ $label }}</dt><dd><span class="ns-num">{{ $value($code) }}</span> {{ $meal->amount($code) !== null ? $unit : '' }}@if ($meal->sourcesOf($code))<button type="button" class="ns-src__open" @click="src = src === '{{ $code }}' ? null : '{{ $code }}'" :aria-expanded="src === '{{ $code }}'">من أين؟</button>@endif</dd></div>
                    @endforeach
                </dl>
                @foreach ($group['shown'] as $code => $v)
                    @if ($meal->sourcesOf($code))
                        <div x-show="src === '{{ $code }}'" x-cloak>@include('site.components.meal-sources', ['code' => $code])</div>
                    @endif
                @endforeach
        @if ($group['folded'])
            </details>
        @else
            </div>
        @endif
    @endforeach
</section>
