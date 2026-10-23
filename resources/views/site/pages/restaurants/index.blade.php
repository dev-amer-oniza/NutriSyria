@extends('site.layouts.app')

@section('title', 'المطاعم')

@section('content')
    <section class="ns-section">
        <div class="ns-wrap ns-meals">
            <div class="ns-section__head">
                <div>
                    <p class="ns-eyebrow">المطاعم</p>
                    <h1 class="t-title">مطاعم نعرف ما في أطباقها</h1>
                    <p class="t-lead ns-muted">كل مطعم بصفحته: عنوانه وتوصيله وطرق التواصل معه، ووجباته بقيمها الغذائية.</p>
                </div>
            </div>

            @include('site.components.search-field', [
                'id' => 'restaurants-q', 'placeholder' => 'ابحث عن مطعم…', 'action' => route('site.brands'), 'value' => $search,
            ])

            <div class="ns-meals__bar">
                <nav class="ns-meals__filter" aria-label="تصفية المطاعم">
                    <a class="ns-chip" href="{{ route('site.brands', array_filter(['q' => $search])) }}" @if (! $deliveryOnly) aria-current="page" @endif>كل المطاعم</a>
                    <a class="ns-chip" href="{{ route('site.brands', array_filter(['q' => $search, 'delivery' => 1])) }}" @if ($deliveryOnly) aria-current="page" @endif>عندهم توصيل</a>
                </nav>
                <p class="ns-meals__count"><span class="ns-num">{{ count($restaurants) }}</span> مطعم</p>
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
                    <p class="t-body ns-muted">
                        @if ($search || $deliveryOnly)
                            لا مطاعم تطابق ما تبحث عنه الآن. جرّب اسماً آخر، أو اعرض كل المطاعم.
                        @else
                            قريباً تظهر هنا المطاعم المسجّلة في NutriSyria.
                        @endif
                    </p>
                </div>
            @endif
        </div>
    </section>
@endsection
