@extends('site.layouts.app')

@section('title', 'الوجبات')

@section('content')
    <section class="ns-section">
        <div class="ns-wrap ns-meals">
            <div class="ns-section__head">
                <div>
                    <p class="ns-eyebrow">الوجبات</p>
                    <h1 class="t-title">وجبات المطاعم، بأرقام نعرف مصدرها</h1>
                    <p class="t-lead ns-muted">كل وجبة هنا راجع أخصائي التغذية قيمها واعتمدها. القيم لكل حصة.</p>
                </div>
            </div>

            @include('site.components.search-field', [
                'id' => 'meals-q', 'placeholder' => 'ابحث عن وجبة أو مطعم…', 'action' => route('site.meals'), 'value' => $search,
            ])

            <div class="ns-meals__bar">
                @if ($personal)
                    <nav class="ns-meals__filter" aria-label="تصفية الوجبات">
                        <a class="ns-chip" href="{{ route('site.meals', array_filter(['q' => $search])) }}" @if (! $onlySuiting) aria-current="page" @endif>كل الوجبات</a>
                        <a class="ns-chip" href="{{ route('site.meals', array_filter(['q' => $search, 'suits' => 1])) }}" @if ($onlySuiting) aria-current="page" @endif>المناسبة لي</a>
                    </nav>
                @elseif (auth()->check())
                    <p class="ns-meals__hint">أكمل ملفك لترى مدى مناسبة كل وجبة لك. <a class="ns-link" href="{{ route('site.criteria') }}">أكمل ملفك</a></p>
                @else
                    <p class="ns-meals__hint">سجّل الدخول لترى مدى مناسبة كل وجبة لجسمك وهدفك. <a class="ns-link" href="{{ route('site.login') }}">سجّل الدخول</a></p>
                @endif
                <p class="ns-meals__count"><span class="ns-num">{{ count($dishes) }}</span> وجبة</p>
            </div>

            @if ($dishes)
                <div class="ns-dishes">
                    @foreach ($dishes as $dish)
                        @include('site.components.dish-card', ['dish' => $dish])
                    @endforeach
                </div>
            @else
                <div class="ns-empty">
                    @include('site.components.chain-tiles', ['pattern' => 'step-4', 'tile' => 40])
                    <p class="t-body ns-muted">
                        @if ($search || $onlySuiting)
                            لا وجبات تطابق ما تبحث عنه الآن. جرّب كلمة أخرى، أو اعرض كل الوجبات.
                        @else
                            قريباً تظهر هنا وجبات المطاعم بعد أن يراجع أخصائي التغذية قيمها ويعتمدها.
                        @endif
                    </p>
                </div>
            @endif
        </div>
    </section>
@endsection
