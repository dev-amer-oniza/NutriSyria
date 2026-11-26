@extends('site.layouts.app')

@section('title', 'المطاعم والبراندات')

@section('content')
    <section class="ns-section">
        <div class="ns-wrap ns-meals">
            <div class="ns-section__head">
                <div>
                    <p class="ns-eyebrow">المطاعم والبراندات</p>
                    <h1 class="t-title">مطاعم وبراندات نعرف ما في أطباقها</h1>
                    <p class="t-lead ns-muted">كلهم أكل صحي. <strong>المطعم</strong> محل بيستقبلك وعنده منيو، و<strong>البراند</strong> علامة بتبيع وجباتها. لكل واحد صفحته: عنوانه وتوصيله وطرق التواصل معه، ووجباته بقيمها الغذائية.</p>
                </div>
            </div>

            @include('site.components.search-field', [
                'id' => 'restaurants-q', 'placeholder' => 'ابحث بالاسم…', 'action' => route('site.brands'), 'value' => $search,
            ])

            <div class="ns-meals__bar">
                {{-- The kind (all / restaurants / brands) and "delivers" combine; each link keeps the other and the search. --}}
                <nav class="ns-meals__filter" aria-label="تصفية المطاعم والبراندات">
                    <a class="ns-chip" href="{{ route('site.brands', array_filter(['q' => $search, 'delivery' => $deliveryOnly ? 1 : null])) }}" @if (! $kind) aria-current="page" @endif>الكل</a>
                    @foreach ($kinds as $one)
                        <a class="ns-chip" href="{{ route('site.brands', array_filter(['q' => $search, 'kind' => $one->value, 'delivery' => $deliveryOnly ? 1 : null])) }}" @if ($kind === $one) aria-current="page" @endif>{{ $one->plural() }}</a>
                    @endforeach
                    <a class="ns-chip" href="{{ route('site.brands', array_filter(['q' => $search, 'kind' => $kind?->value, 'delivery' => $deliveryOnly ? null : 1])) }}" aria-pressed="{{ $deliveryOnly ? 'true' : 'false' }}">عندهم توصيل</a>
                </nav>
                <p class="ns-meals__count"><span class="ns-num">{{ $page->total() }}</span> {{ $kind?->getLabel() ?? 'مطعم وبراند' }}</p>
            </div>

            @if ($page->isNotEmpty())
                <div class="ns-rests">
                    @foreach ($page as $restaurant)
                        @include('site.components.restaurant-card', ['restaurant' => $restaurant])
                    @endforeach
                </div>
                @include('site.partials.pager', ['page' => $page])
            @else
                <div class="ns-empty">
                    <img class="ns-empty__mark" src="{{ asset('images/site/restaurant-cover.svg') }}" alt="" loading="lazy">
                    <p class="t-body ns-muted">
                        @if ($search || $deliveryOnly || $kind)
                            لا شيء يطابق ما تبحث عنه الآن. جرّب اسماً آخر، أو اعرض الكل.
                        @else
                            قريباً تظهر هنا المطاعم والبراندات المسجّلة في NutriSyria.
                        @endif
                    </p>
                </div>
            @endif
        </div>
    </section>
@endsection
