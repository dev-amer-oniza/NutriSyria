{{-- Param $restaurants (App\Data\Site\RestaurantFacts, most meals first). --}}
<section class="ns-section" aria-labelledby="restaurants-title">
    <div class="ns-wrap">
        <div class="ns-section__head">
            <div>
                <p class="ns-eyebrow">المطاعم</p>
                <h2 class="t-title" id="restaurants-title">مطاعم معنا</h2>
                <p class="t-lead ns-muted">مطاعم تشارك وصفاتها، فنحسب قيم وجباتها ويعتمدها أخصائي التغذية.</p>
            </div>
            <a class="ns-link" href="{{ route('site.brands') }}">كل المطاعم@include('site.partials.icon', ['name' => 'arrow', 'size' => 'sm'])</a>
        </div>

        @if ($restaurants)
            <div class="ns-rests">
                @foreach ($restaurants as $restaurant)
                    @include('site.components.restaurant-card', ['restaurant' => $restaurant])
                @endforeach
            </div>
        @else
            <div class="ns-empty">
                @include('site.components.chain-tiles', ['pattern' => 'step-4', 'tile' => 40])
                <p class="t-body ns-muted">قريباً تظهر هنا المطاعم المسجّلة في NutriSyria.</p>
            </div>
        @endif
    </div>
</section>
