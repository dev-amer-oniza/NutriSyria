{{--
    One person's verdict on one meal (MealFit, or an example person on the home page).
    Params: $level 1..4, $reasons (list), $notes (list, optional), $share (energy, protein,
    carbs, fat: percent or null), $portion ({grams, kcal, whole?} or null), $who (optional),
    $info (rules not verified yet, shown only), $grounds ("قيّمناها على: …"), $general (bool),
    $sources (the /sources card of the person's limits, for "من أين هذا الحد؟").
--}}
@include('site.components.fit-verdict', ['level' => $level])
@if (! empty($general))<p class="ns-fit__general">على احتياجك العام</p>@endif
@isset($who)<p class="ns-fit__who">{{ $who }}</p>@endisset

<ul class="ns-reasons">
    @foreach ($reasons as $reason)
        <li>{{ $reason }}</li>
    @endforeach
</ul>

@if (! empty($info))
    <ul class="ns-fit__info">
        @foreach ($info as $line)
            <li>{{ $line }}</li>
        @endforeach
    </ul>
@endif

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

@if (! empty($grounds))<p class="ns-fit__grounds">{{ $grounds }}@if (! empty($sources)) <a class="ns-link" href="{{ route('site.sources') }}#{{ $sources }}">من أين هذا الحد؟</a>@endif</p>@endif
