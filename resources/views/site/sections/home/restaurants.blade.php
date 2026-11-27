{{-- Param $restaurants (App\Data\Site\RestaurantFacts, most meals first). --}}
<section class="ns-section ns-tint ns-tint--sand" aria-labelledby="restaurants-title">
    <div class="ns-wrap">
        <div class="ns-section__head">
            <div>
                <p class="ns-eyebrow">المطاعم والبراندات</p>
                <h2 class="t-title" id="restaurants-title">مطاعم وبراندات معنا</h2>
                <p class="t-lead ns-muted">مطاعم وبراندات أكل صحي تشارك وصفاتها، فنحسب قيم وجباتها ويعتمدها مراجع الوصفات.</p>
            </div>
            <a class="ns-btn ns-btn--secondary ns-btn--sm ns-more-btn" href="{{ route('site.brands') }}">كل المطاعم والبراندات@include('site.partials.icon', ['name' => 'arrow', 'size' => 'sm'])</a>
        </div>

        @if ($restaurants)
            <div class="ns-rests">
                @foreach ($restaurants as $restaurant)
                    @include('site.components.restaurant-card', ['restaurant' => $restaurant])
                @endforeach
            </div>
        @else
            <div class="ns-empty">
                <img class="ns-empty__mark" src="{{ asset('images/site/restaurant-cover.svg') }}" alt="" loading="lazy">
                <p class="t-body ns-muted">قريباً تظهر هنا المطاعم المسجّلة في NutriSyria.</p>
            </div>
        @endif
    </div>
</section>
