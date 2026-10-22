{{--
    One person's verdict on one meal (MealFit, or an example person on the home page).
    Params: $level 1..4, $reasons (list), $notes (list, optional), $share (energy, protein,
    carbs, fat: percent or null), $portion ({grams, kcal, whole?} or null), $who (optional).
--}}
@include('site.components.fit-verdict', ['level' => $level])
@isset($who)<p class="ns-fit__who">{{ $who }}</p>@endisset

<ul class="ns-reasons">
    @foreach ($reasons as $reason)
        <li>{{ $reason }}</li>
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

<p class="ns-portion">
    <b>الحصة المناسبة</b>
    @if ($portion === null)
        <span>لا ننصح بها لك الآن</span>
    @elseif ($portion['whole'] ?? false)
        <span>الحصة كاملة: <span class="ns-num">{{ $portion['grams'] }}</span> غ</span>
    @else
        <span><span class="ns-num">{{ $portion['grams'] }}</span> غ@if ($portion['kcal'] !== null) · <span class="ns-num">{{ $portion['kcal'] }}</span> سعرة@endif</span>
    @endif
</p>
