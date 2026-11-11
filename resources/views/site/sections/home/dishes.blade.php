{{--
    "استكشف الوجبات": a banner, then the newest approved meals as photo tiles (owner's decision
    1a: real meals, not general categories). Param $dishes: meal cards (MealFacts::card).
--}}
<section class="ns-explore" aria-labelledby="dishes-title">
    <div class="ns-wrap ns-explore__in">
        <div class="ns-banner">
            <h2 class="ns-banner__title">تغذية أفضل<br>لحياة أكثر توازناً</h2>
            <p class="ns-banner__text">اختر هدفك الصحي، ونحسب لك مدى مناسبة كل وجبة له.</p>
            <a class="ns-btn ns-btn--primary" href="{{ route('site.goals') }}">اكتشف المزيد@include('site.partials.icon', ['name' => 'arrow', 'size' => 'sm'])</a>
        </div>

        <div class="ns-explore__meals">
            <div class="ns-explore__head">
                <h2 class="ns-explore__title" id="dishes-title">استكشف الوجبات</h2>
                <a class="ns-link" href="{{ route('site.meals') }}">عرض الكل@include('site.partials.icon', ['name' => 'arrow', 'size' => 'sm'])</a>
            </div>

            @if ($dishes)
                <ul class="ns-explore__strip">
                    @foreach (array_slice($dishes, 0, 5) as $i => $dish)
                        <li class="ns-xtile">
                            <div class="ns-xtile__cover @if (! $dish['image']) ns-xtile__cover--tiles ns-xtile__cover--{{ $i % 4 + 1 }} @endif">
                                @if ($dish['image'])
                                    <img src="{{ $dish['image'] }}" alt="" loading="lazy">
                                @endif
                                @if ($dish['fit'] ?? null)
                                    <span class="ns-xtile__fit">@include('site.components.fit-verdict', ['level' => $dish['fit'], 'compact' => true])</span>
                                @endif
                            </div>
                            <div class="ns-xtile__foot">
                                <a class="ns-xtile__name" href="{{ $dish['url'] }}">{{ $dish['name'] }}</a>
                                <span class="ns-xtile__meta"><span class="ns-num">{{ $dish['kcal'] ?? '—' }}</span> سعرة · {{ $dish['brand'] }}</span>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @else
                <div class="ns-empty">
                    <p class="t-body ns-muted">قريباً تظهر هنا وجبات المطاعم بعد أن يراجع أخصائي التغذية قيمها ويعتمدها.</p>
                </div>
            @endif
        </div>
    </div>
</section>
