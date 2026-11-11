{{-- A restaurant in a list. Param $restaurant (App\Data\Site\RestaurantFacts). The logo, or the one cover every restaurant without a logo shares. --}}
<a class="ns-rest" href="{{ route('site.brands.show', $restaurant->id) }}">
    <span class="ns-rest__logo">
        @if ($restaurant->logo)
            <img src="{{ $restaurant->logo }}" alt="شعار {{ $restaurant->name }}" loading="lazy">
        @else
            <img src="{{ asset('images/site/restaurant-cover.svg') }}" alt="">
        @endif
    </span>
    <span class="ns-rest__body">
        <span class="ns-rest__name">{{ $restaurant->name }}</span>
        @if ($restaurant->city)<span class="ns-rest__meta">{{ $restaurant->city }}</span>@endif
        <span class="ns-rest__tags">
            <span class="ns-rest__tag"><span class="ns-num">{{ $restaurant->mealCount }}</span> وجبة بقيم معتمدة</span>
            @if ($restaurant->hasDelivery)<span class="ns-rest__tag ns-rest__tag--delivery">توصيل</span>@endif
        </span>
    </span>
</a>
