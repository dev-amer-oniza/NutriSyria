{{-- A restaurant in a list. Param $restaurant (App\Data\Site\RestaurantFacts). The logo, or the identity tiles. --}}
<a class="ns-rest" href="{{ route('site.brands.show', $restaurant->id) }}">
    <span class="ns-rest__logo">
        @if ($restaurant->logo)
            <img src="{{ $restaurant->logo }}" alt="شعار {{ $restaurant->name }}" loading="lazy">
        @else
            @include('site.components.chain-tiles', ['pattern' => \App\Support\Site\ChainPatterns::dishCover($restaurant->id), 'tile' => 24])
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
