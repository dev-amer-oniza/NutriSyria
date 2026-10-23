@php($register = route('site.register'))

<section class="ns-section" aria-labelledby="goals-title">
    <div class="ns-wrap">
        <div class="ns-goals">
            <div>
                <p class="ns-eyebrow">أهداف صحية</p>
                <h2 class="t-title" id="goals-title">ابدأ من هدفك</h2>
                <p class="t-lead ns-muted">اختر ما يهمّك الآن، ونحسب لك مدى مناسبة كل وجبة له.</p>
            </div>
            <div class="ns-goals__chips">
                @foreach (config('site.goals') as $key => $goal)
                    <a class="ns-chip" href="{{ route('site.goals') }}#{{ $key }}">@include('site.partials.icon', ['name' => $goal['icon']]){{ $goal['label'] }}</a>
                @endforeach
            </div>
        </div>

        <div class="ns-cta">
            <div class="ns-cta__copy">
                <h2 class="ns-cta__title">اعرف أي الوجبات تناسبك</h2>
                <p class="t-body ns-muted">أدخل بياناتك مرة واحدة، فنحسب لك مدى مناسبة كل وجبة والحصة الأنسب لك. بياناتك الصحية لك وحدك، ويمكنك حذفها متى شئت.</p>
                <div class="ns-cta__actions">
                    <a class="ns-btn ns-btn--primary" href="{{ $register }}">أنشئ حسابك</a>
                    <a class="ns-btn ns-btn--secondary" href="{{ route('site.meals') }}">تصفّح الوجبات أولاً</a>
                </div>
            </div>
            @include('site.components.chain-tiles', ['pattern' => 'cta', 'tile' => 72, 'class' => 'ns-cta__art'])
        </div>
    </div>
</section>
