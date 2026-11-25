@extends('site.layouts.app')

@section('title', 'يومي')

{{--
    "يومي": the person's day (DailyView). The week on top, then what is left of today's needs
    and what was eaten side by side, then the dishes that suit this hour's meal (today only).
    "أكلت شي مو موجود" opens the request sheet (Alpine `sheet`); it opens by itself after a
    form error, or with ?sheet=request&name=… from the meals search.
--}}
@php
    $openSheet = ($errors->has('food_name') || $errors->has('ingredients') || request('sheet') === 'request') && $day->isToday ? 'request' : null;
    $openSheet ??= $errors->has('weight_kg') || request('sheet') === 'weight' ? 'weight' : null;
    $requested = session('requested');
@endphp

@section('content')
    <section class="ns-section ns-daily-page" x-data="{ sheet: @js($openSheet) }">
        <div class="ns-wrap">
            <div class="ns-daily__head">
                <div>
                    <p class="ns-eyebrow">حسابي</p>
                    <h1 class="t-title">يومي</h1>
                    <p class="t-lead ns-muted">
                        {{ \App\Support\Site\SyrianDate::long($day->date) }}
                        @unless ($day->isToday)
                            · <a class="ns-link" href="{{ route('site.daily') }}">رجوع لليوم</a>
                        @endunless
                    </p>
                </div>

                <div class="ns-weeknav">
                    <div class="ns-weeknav__bar">
                        @if ($day->previousWeek)
                            <a class="ns-btn ns-btn--secondary ns-btn--sm ns-weeknav__go" href="{{ route('site.daily', ['date' => $day->previousWeek->toDateString()]) }}" rel="prev">
                                @include('site.partials.icon', ['name' => 'back'])<span class="ns-weeknav__word">الأسبوع </span>الماضي
                            </a>
                        @else
                            <span class="ns-weeknav__spacer" aria-hidden="true"></span>
                        @endif
                        <p class="ns-weeknav__range">
                            {{ \App\Support\Site\SyrianDate::range($day->week[0]['date'], $day->week[6]['date']) }}
                        </p>
                        @if ($day->nextWeek)
                            <a class="ns-btn ns-btn--secondary ns-btn--sm ns-weeknav__go" href="{{ $day->nextWeek->isSameDay(\App\Services\Daily\DailyLog::now()) ? route('site.daily') : route('site.daily', ['date' => $day->nextWeek->toDateString()]) }}" rel="next">
                                <span class="ns-weeknav__word">الأسبوع </span>الجاي@include('site.partials.icon', ['name' => 'arrow'])
                            </a>
                        @else
                            <span class="ns-weeknav__spacer" aria-hidden="true"></span>
                        @endif
                    </div>
                    <nav class="ns-week" aria-label="أيام الأسبوع">
                        @foreach ($day->week as $d)
                            @if ($d['future'])
                                <span class="ns-week__day is-future" aria-disabled="true">{{ \App\Support\Site\SyrianDate::shortDay($d['date']) }}<b class="ns-num">{{ $d['date']->day }}</b><i></i></span>
                            @else
                                <a @class(['ns-week__day', 'is-logged' => $d['logged'], 'is-today' => $d['today'], 'is-current' => $d['date']->equalTo($day->date)])
                                   href="{{ $d['today'] ? route('site.daily') : route('site.daily', ['date' => $d['date']->toDateString()]) }}"
                                   @if ($d['date']->equalTo($day->date)) aria-current="date" @endif>
                                        {{ \App\Support\Site\SyrianDate::shortDay($d['date']) }}<b class="ns-num">{{ $d['date']->day }}</b><i title="{{ $d['logged'] ? 'سجّلت فيه' : '' }}"></i>
                                </a>
                            @endif
                        @endforeach
                    </nav>
                </div>
            </div>

            @if ($requested)
                <div class="ns-requested" role="status">
                    <span class="ns-requested__mark">@include('site.partials.icon', ['name' => 'leaf'])</span>
                    <div>
                        <p class="ns-requested__title">وصلنا طلبك: «{{ $requested }}»</p>
                        <p class="ns-requested__text">رح نشتغل على توافرها، ونخبّرك بالجرس لما تصير موجودة. لحد هداك الوقت بتبين بيومك بلا ما تنحسب.</p>
                    </div>
                </div>
            @endif

            <div class="ns-daily">
                @if ($day->needs !== null)
                    @include('site.components.daily.today-card', ['day' => $day])
                @else
                    <section class="ns-today ns-today--setup">
                        <span class="ns-today__setup-mark">@include('site.partials.icon', ['name' => 'target'])</span>
                        <div>
                            <h2 class="ns-today__label">قديش باقيلك اليوم؟</h2>
                            <p class="ns-today__sub">كمّل ملفك (العمر والطول والوزن والنشاط والهدف) لنحسب احتياجك ونقلك قديش باقيلك كل يوم.</p>
                            <a class="ns-btn ns-btn--primary" href="{{ route('site.criteria') }}">كمّل ملفك</a>
                        </div>
                    </section>
                @endif

                @include('site.components.daily.eaten-list', ['day' => $day])
                @include('site.components.daily.weight-card', ['weight' => $weight])
            </div>

            @if ($day->isToday && $day->needs !== null)
                <section class="ns-daily__suggest ns-tint ns-tint--sage" aria-labelledby="suggest-title">
                    <div class="ns-daily__suggest-head">
                        <div>
                            <p class="ns-eyebrow">{{ $type->label() }}</p>
                            <h2 class="t-heading" id="suggest-title">شو بيناسبك على {{ $type->onLabel() }}؟</h2>
                            <p class="ns-muted">
                                @if ($day->left() > 0)
                                    باقيلك <span class="ns-num">{{ number_format($day->left()) }}</span> سعرة اليوم. هدول بيناسبوك وبيلحقوا بالباقي:
                                @else
                                    خلّصت احتياجك اليوم. إذا جعت، اختار شي خفيف.
                                @endif
                            </p>
                        </div>
                        <a class="ns-btn ns-btn--secondary ns-btn--sm ns-more-btn" href="{{ route('site.meals', ['suits' => 1]) }}">كل ما يناسبك @include('site.partials.icon', ['name' => 'arrow'])</a>
                    </div>

                    @if ($suggestions)
                        <div class="ns-dishes ns-daily__dishes">
                            @foreach ($suggestions as $dish)
                                @include('site.components.dish-card', ['dish' => $dish])
                            @endforeach
                        </div>
                    @else
                        <div class="ns-daily__none">
                            <span class="ns-eaten__empty-mark">@include('site.partials.icon', ['name' => 'bowl'])</span>
                            <p>ما لقينا وجبة على {{ $type->onLabel() }} بتلحق بالباقيلك اليوم. شوف <a class="ns-link" href="{{ route('site.meals', ['suits' => 1]) }}">كل الوجبات اللي بتناسبك</a>.</p>
                        </div>
                    @endif
                </section>
            @endif
        </div>

        @if ($day->isToday)
            @include('site.components.daily.request-sheet', ['type' => $type])
        @endif
        @include('site.components.daily.weight-sheet', ['weight' => $weight, 'managed' => $managed])
    </section>
@endsection
