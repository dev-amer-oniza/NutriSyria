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
            @include('site.components.chain-tiles', ['pattern' => 'cta', 'tile' => 56, 'class' => 'ns-method__art'])
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
                <h2 class="t-title" id="next-title">لا نقول "وجبة مناسبة لمرضى السكري"</h2>
                <p class="t-body ns-muted">نفس الطبق قد يناسب شخصاً ولا يناسب آخر عنده نفس الهدف، حسب جسمه ونشاطه وحالته. لذلك نقيّم كل وجبة لك أنت. {{ $next['text'] }}</p>
            </div>
            <a class="ns-btn ns-btn--primary" href="{{ $next['url'] }}">{{ $next['label'] }}</a>
        </div>
    </section>

    <section class="ns-section ns-section--sand" aria-labelledby="conditions-title">
        <div class="ns-wrap">
            <div class="ns-section__head">
                <div>
                    <p class="ns-eyebrow">الحالات الصحية</p>
                    <h2 class="t-title" id="conditions-title">كل الحالات التي نحسب لها</h2>
                    <p class="t-lead ns-muted">حين تضيف حالة إلى سجلك الصحي، نختار القالب المناسب لها ونطبّق حدودها. وإن كانت عندك أكثر من حالة، يغلب الحد الأشد.</p>
                </div>
            </div>
            <div class="ns-conditions">
                @foreach ($conditions as $group)
                    <section class="ns-conditions__group" aria-label="{{ $group['label'] }}">
                        <h3 class="ns-feature__title">{{ $group['label'] }}</h3>
                        <dl>
                            @foreach ($group['conditions'] as $condition)
                                <div class="ns-condition">
                                    <dt>{{ $condition['label'] }}@if ($condition['specialist']) <span class="ns-condition__tag">ننصح بأخصائي</span>@endif</dt>
                                    <dd>@if ($condition['template'])<span class="ns-condition__template">{{ $condition['template'] }}:</span> @endif{{ $condition['text'] }}</dd>
                                </div>
                            @endforeach
                        </dl>
                    </section>
                @endforeach
            </div>
        </div>
    </section>

    <section class="ns-section" aria-labelledby="doctor-title">
        <div class="ns-wrap ns-method__split">
            <div>
                <p class="ns-eyebrow">قبل أن تبدأ</p>
                <h2 class="t-title" id="doctor-title">هذا ليس بديلاً عن طبيبك</h2>
                <p class="t-body">أرقامنا محسوبة بمعادلات عامة معتمدة عالمياً، وتساعدك على الاختيار. لكنها لا تشخّص ولا تعالج. إن كانت عندك حالة صحية، فالقرار لطبيبك وأخصائي التغذية.</p>
            </div>
            @include('site.components.nutritionist-cta')
        </div>
    </section>
@endsection
