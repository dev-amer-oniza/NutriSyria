{{--
    One person's verdict on one meal (MealFit, or an example person on the home page), simple
    first (stage 3, decisions 1a 2a 3a 4a): the verdict, the plate (whole, ¾, ½, ¼), one short
    reason with "من أين هذا الحد؟", what to change, then "تفاصيل أكثر" with everything else.
    Params: $level 1..4, $headline (the leading reason; else the first of $reasons), $why
    ({steps, refs} or null), $reasons (list), $notes (list, optional), $share (energy, protein,
    carbs, fat: percent or null), $portion ({fraction, grams, kcal, whole} or null), $who
    (optional), $meal (MealFacts), $causes (reason index => nutrient), for "من أين الدهون؟"
    under a reason; $fitSuggestions (MealSuggestion list) and $editor (inside "اطبخها على
    طريقتك"), for "ماذا تستطيع أن تفعل".
--}}
@php
    $headline = ($headline ?? '') !== '' ? $headline : ($reasons[0] ?? '');
    $refs = collect($why['refs'] ?? [])->map(fn ($key) => config("nutrition_references.$key"))->filter()
        ->unique(fn ($ref) => $ref['org'].$ref['year'])->values();
@endphp
@include('site.components.fit-verdict', ['level' => $level])

<div class="ns-plate">
    @if ($portion === null)
        <span class="ns-plate__pie ns-plate__pie--none" aria-hidden="true"></span>
        <p><b>لا ننصح بها لك الآن</b></p>
    @else
        <span class="ns-plate__pie" style="--fill: {{ (int) round(($portion['fraction'] ?? 1) * 100) }}%" aria-hidden="true"></span>
        @if ($portion['per100g'] ?? false)
            {{-- No serving weight: the values are per 100 g, so the grams say it, not a plate. --}}
            <p><b>كُل حتى <span class="ns-num">{{ $portion['grams'] }}</span> غ</b><span>لا نعرف وزن صحنها، فالحساب لكل 100 غ.</span></p>
        @else
            <p>
                <b>كُل {{ \App\Services\Nutrition\Fit\MealFitCalculator::plate($portion['fraction'] ?? 1) }}</b>
                <span>حوالي <span class="ns-num">{{ $portion['grams'] }}</span> غ@if (($portion['kcal'] ?? null) !== null) · <span class="ns-num">{{ $portion['kcal'] }}</span> سعرة@endif</span>
            </p>
        @endif
    @endif
</div>

<p class="ns-fit__headline">{{ $headline }}</p>

@if (! empty($why['steps']))
    <details class="ns-why">
        <summary>من أين هذا الحد؟</summary>
        <ol>
            @foreach ($why['steps'] as $step)
                <li>{{ $step }}</li>
            @endforeach
        </ol>
        @if ($refs->isNotEmpty())
            <p class="ns-why__refs">المرجع:
                @foreach ($refs as $ref)
                    <a href="{{ $ref['url'] }}" rel="noopener" target="_blank">{{ $ref['org'] }} {{ $ref['year'] }}</a>@if (! $loop->last)، @endif
                @endforeach
            </p>
        @endif
    </details>
@endif

@if (($meal ?? null) instanceof \App\Data\Nutrition\MealFacts && ! empty($fitSuggestions))
    @include('site.components.meal-suggestions', ['suggestions' => $fitSuggestions, 'meal' => $meal, 'editor' => $editor ?? false])
@endif

<details class="ns-more">
    <summary>تفاصيل أكثر</summary>
    @isset($who)<p class="ns-fit__who">{{ $who }}</p>@endisset

    <ul class="ns-reasons">
        @foreach ($reasons as $i => $reason)
            <li>
                {{ $reason }}
                {{-- "ليش؟": the ingredients behind a nutrient that went over. --}}
                @if (($meal ?? null) instanceof \App\Data\Nutrition\MealFacts && isset($causes[$i]))
                    @include('site.components.meal-sources', ['meal' => $meal, 'code' => $causes[$i]])
                @endif
            </li>
        @endforeach
    </ul>

    @if (! empty($notes))
        <ul class="ns-fit__notes">
            @foreach ($notes as $note)
                <li>{{ $note }}</li>
            @endforeach
        </ul>
    @endif

    <div>
        <p class="ns-fit__share-title">نسبة الحصة من الاحتياج اليومي</p>
        @include('site.components.daily-share', ['share' => $share])
    </div>
</details>
