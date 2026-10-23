{{--
    Meal card. Param $dish: name, brand, brandUrl (the restaurant's page, or null), description,
    portion (g), kcal, protein, carbs, fat (null when missing), verification, image (url or null),
    url, key, fit (the signed-in person's level 1..4, or null). $showBrand: false on the
    restaurant's own page. The whole card opens the meal (a stretched link on the name); the
    restaurant's name is its own link above it.
    Shows the restaurant's photo when there is one, otherwise the identity tiles.
--}}
@php($showBrand = $showBrand ?? true)
<article class="ns-dish">
    <div class="ns-dish__cover">
        @if ($dish['image'])
            <img src="{{ $dish['image'] }}" alt="{{ $dish['name'] }}، {{ $dish['brand'] }}" loading="lazy">
        @else
            @include('site.components.chain-tiles', ['pattern' => \App\Support\Site\ChainPatterns::dishCover($dish['key'])])
        @endif
    </div>
    <div class="ns-dish__body">
        <div class="ns-dish__meta">
            @include('site.components.verify-badge', ['level' => $dish['verification']])
            <span>حصة <span class="ns-num">{{ $dish['portion'] }}</span> غ</span>
        </div>
        <div class="ns-dish__head">
            <h3 class="ns-dish__name"><a class="ns-dish__link" href="{{ $dish['url'] }}">{{ $dish['name'] }}</a></h3>
            <span class="ns-dish__kcal"><span class="ns-num">{{ $dish['kcal'] ?? '—' }}</span><span>سعرة</span></span>
        </div>
        @if ($showBrand)
            <p class="ns-dish__brand">
                @if ($dish['brandUrl'] ?? null)
                    <a class="ns-brand-link" href="{{ $dish['brandUrl'] }}">{{ $dish['brand'] }}</a>
                @else
                    {{ $dish['brand'] }}
                @endif
            </p>
        @endif
        @if ($dish['fit'] ?? null)
            @include('site.components.fit-verdict', ['level' => $dish['fit'], 'compact' => true])
        @endif
        @if ($dish['description'])<p class="ns-dish__desc">{{ $dish['description'] }}</p>@endif
        <dl class="ns-dish__macros">
            @foreach (['protein' => 'بروتين', 'carbs' => 'كربوهيدرات', 'fat' => 'دهون'] as $field => $label)
                <div>
                    <dt>{{ $label }}</dt>
                    <dd><span class="ns-num">{{ $dish[$field] ?? '—' }}</span> غ</dd>
                </div>
            @endforeach
        </dl>
    </div>
</article>
