@extends('site.layouts.app')

@section('title', $restaurant->name)

@section('content')
    @php
        $details = array_filter([
            'المحافظة' => $restaurant->city,
            'العنوان' => $restaurant->address,
            'ساعات الدوام' => $restaurant->openingHours,
            'التوصيل' => $restaurant->hasDelivery ? ($restaurant->deliveryNote ?? 'متوفر') : null,
        ]);
    @endphp

    <section class="ns-section">
        <div class="ns-wrap ns-restaurant">
            <a class="ns-link" href="{{ route('site.brands') }}">كل المطاعم</a>

            <header class="ns-restaurant__head">
                <span class="ns-restaurant__logo">
                    @if ($restaurant->logo)
                        <img src="{{ $restaurant->logo }}" alt="شعار {{ $restaurant->name }}">
                    @else
                        @include('site.components.chain-tiles', ['pattern' => \App\Support\Site\ChainPatterns::dishCover($restaurant->id), 'tile' => 40])
                    @endif
                </span>
                <div>
                    <p class="ns-eyebrow">مطعم</p>
                    <h1 class="t-title">{{ $restaurant->name }}</h1>
                    <p class="ns-rest__tags">
                        <span class="ns-rest__tag"><span class="ns-num">{{ $restaurant->mealCount }}</span> وجبة بقيم معتمدة</span>
                        @if ($restaurant->hasDelivery)<span class="ns-rest__tag ns-rest__tag--delivery">توصيل</span>@endif
                    </p>
                    @if ($restaurant->description)<p class="t-body ns-restaurant__about">{{ $restaurant->description }}</p>@endif
                </div>
            </header>

            @if ($details || $restaurant->hasContact())
                <div class="ns-restaurant__info">
                    @if ($details)
                        <dl class="ns-restaurant__details">
                            @foreach ($details as $label => $value)
                                <div><dt>{{ $label }}</dt><dd>{{ $value }}</dd></div>
                            @endforeach
                        </dl>
                    @endif
                    @if ($restaurant->hasContact())
                        <div class="ns-restaurant__contact" aria-label="التواصل">
                            @foreach ($restaurant->phones as $phone)
                                <a class="ns-btn ns-btn--secondary ns-btn--sm" href="{{ $phone['href'] }}" dir="ltr">{{ $phone['label'] }}</a>
                            @endforeach
                            @if ($restaurant->whatsappUrl)
                                <a class="ns-btn ns-btn--primary ns-btn--sm" href="{{ $restaurant->whatsappUrl }}" target="_blank" rel="noopener">واتساب</a>
                            @endif
                            @if ($restaurant->instagramUrl)
                                <a class="ns-btn ns-btn--ghost ns-btn--sm" href="{{ $restaurant->instagramUrl }}" target="_blank" rel="noopener nofollow">إنستغرام</a>
                            @endif
                            @if ($restaurant->facebookUrl)
                                <a class="ns-btn ns-btn--ghost ns-btn--sm" href="{{ $restaurant->facebookUrl }}" target="_blank" rel="noopener nofollow">فيسبوك</a>
                            @endif
                        </div>
                    @endif
                </div>
            @endif

            <section aria-labelledby="menu-title">
                <h2 class="t-heading" id="menu-title">الوجبات</h2>
                @if ($dishes)
                    <div class="ns-dishes">
                        @foreach ($dishes as $dish)
                            @include('site.components.dish-card', ['dish' => $dish, 'showBrand' => false])
                        @endforeach
                    </div>
                @else
                    <p class="t-body ns-muted">لم تُعتمد قيم أي وجبة من هذا المطعم بعد.</p>
                @endif
            </section>

            @if ($pending)
                <section aria-labelledby="pending-title">
                    <h2 class="t-heading" id="pending-title">قيمها قيد المراجعة</h2>
                    <p class="t-body ns-muted">من منيو المطعم، يراجع أخصائي التغذية قيمها قبل أن تظهر.</p>
                    <ul class="ns-pending">
                        @foreach ($pending as $product)
                            <li class="ns-pending__item">
                                @if ($product['image'])<img src="{{ $product['image'] }}" alt="" loading="lazy">@endif
                                <span>
                                    <span class="ns-pending__name">{{ $product['name'] }}</span>
                                    @if ($product['description'])<span class="ns-pending__desc">{{ $product['description'] }}</span>@endif
                                </span>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif
        </div>
    </section>
@endsection
