@extends('site.layouts.app')

@section('title', 'الأهداف الصحية')

@section('content')
    <section class="ns-section">
        <div class="ns-wrap ns-method__hero">
            <div>
                <p class="ns-eyebrow">الأهداف الصحية</p>
                <h1 class="t-display">هدفك يغيّر الأرقام، لا الكلام</h1>
                <p class="t-lead ns-muted">لكل هدف نقول لك بالضبط ما الذي يتغيّر في حساباتنا: السعرات، والحدود، وكيف نقيّم كل وجبة لك.</p>
                <nav class="ns-goals__chips" aria-label="الأهداف">
                    @foreach ($goals as $goal)
                        <a class="ns-chip" href="#{{ $goal['key'] }}">@include('site.partials.icon', ['name' => $goal['icon']]){{ $goal['label'] }}</a>
                    @endforeach
                </nav>
            </div>
            <img class="ns-method__art ns-figure" src="{{ asset('images/site/fit-figure.svg') }}" alt="" width="400" height="300" loading="lazy">
        </div>
    </section>

    <section class="ns-section ns-section--sand" aria-label="الأهداف بالتفصيل">
        <div class="ns-wrap ns-goals-page__list">
            @foreach ($goals as $goal)
                <article class="ns-goal" id="{{ $goal['key'] }}" aria-labelledby="{{ $goal['key'] }}-title">
                    <span class="ns-feature__icon">@include('site.partials.icon', ['name' => $goal['icon']])</span>
                    <div>
                        <h2 class="ns-goal__title" id="{{ $goal['key'] }}-title">{{ $goal['label'] }}</h2>
                        <p class="t-body">{{ $goal['lead'] }}</p>
                        <ul class="ns-goal__rules">
                            @foreach ($goal['rules'] as $rule)<li>{{ $rule }}</li>@endforeach
                        </ul>
                        <p class="ns-goal__how"><strong>كيف تفعّله:</strong> {{ $goal['how'] }}</p>
                        @if ($goal['note'])<p class="ns-method__example">{{ $goal['note'] }}</p>@endif
                    </div>
                </article>
            @endforeach
        </div>
    </section>

    <section class="ns-section" aria-labelledby="next-title">
        <div class="ns-wrap ns-partners__cta">
            <div>
                <h2 class="t-title" id="next-title">لا نقول "وجبة مناسبة لإنقاص الوزن"</h2>
                <p class="t-body ns-muted">نفس الطبق قد يناسب شخصاً ولا يناسب آخر عنده نفس الهدف، حسب جسمه ونشاطه. لذلك نقيّم كل وجبة لك أنت. {{ $next['text'] }}</p>
            </div>
            <a class="ns-btn ns-btn--primary" href="{{ $next['url'] }}">{{ $next['label'] }}</a>
        </div>
    </section>

    <section class="ns-section" aria-labelledby="doctor-title">
        <div class="ns-wrap ns-method__split">
            <div>
                <p class="ns-eyebrow">قبل أن تبدأ</p>
                <h2 class="t-title" id="doctor-title">هذا ليس بديلاً عن طبيبك</h2>
                <p class="t-body">أرقامنا محسوبة بمعادلات عامة معتمدة عالمياً، وتساعدك على الاختيار. لكنها لا تشخّص ولا تعالج. NutriSyria حالياً للبالغين الأصحاء ومن يمارسون الرياضة: إن كانت عندك حالة صحية، أو كنتِ حاملاً أو مرضعة، فالقرار لطبيبك وأخصائي التغذية.</p>
            </div>
            @include('site.components.nutritionist-cta')
        </div>
    </section>
@endsection
