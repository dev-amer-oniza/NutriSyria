@extends('site.layouts.app')

@section('title', 'المطبخ السوري')

@section('content')
    <section class="ns-section">
        <div class="ns-wrap ns-restaurant">
            <header class="ns-restaurant__head">
                <span class="ns-restaurant__logo">
                    <img src="{{ asset('images/site/kitchen-cover.svg') }}" alt="" loading="lazy">
                </span>
                <div>
                    <p class="ns-eyebrow">من المنصة</p>
                    <h1 class="t-title">المطبخ السوري</h1>
                    <p class="ns-rest__tags">
                        <span class="ns-rest__tag"><span class="ns-num">{{ $count }}</span> وجبة بقيم محسوبة</span>
                    </p>
                    <p class="t-body ns-restaurant__about">{{ $kitchen->description ?: 'أكلات البيت السوري بقيمها الغذائية، من وصفات المنصة.' }}</p>
                    <p class="t-body ns-muted ns-kitchen__note">نحسب قيم كل وصفة من مكوّنات موثّقة المصدر وفق كمياتها. وحين تدخل، نقول لك إن كانت الطبخة تناسبك وبأي كمية.</p>
                </div>
            </header>

            <section class="ns-menu" aria-labelledby="menu-title">
                <h2 class="t-heading" id="menu-title">الوجبات</h2>
                @include('site.components.menu-sections', ['sections' => $sections, 'empty' => 'نجهّز وصفات المطبخ السوري، وتظهر هنا قريباً.'])
            </section>
        </div>
    </section>
@endsection
