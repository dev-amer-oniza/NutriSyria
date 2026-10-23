{{-- "Meals that suit you". Params: $suits (dish cards, best first; null when there is no profile to judge on). --}}
<section class="ns-section" aria-labelledby="suits-title">
    <div class="ns-wrap">
        <div class="ns-section__head">
            <div>
                <p class="ns-eyebrow">لك أنت</p>
                <h2 class="t-title" id="suits-title">وجبات تناسبك</h2>
                <p class="t-lead ns-muted">مختارة من معاييرك أنت: الأنسب أولاً.</p>
            </div>
            @if ($suits)
                <a class="ns-link" href="{{ route('site.meals', ['suits' => 1]) }}">كل ما يناسبك@include('site.partials.icon', ['name' => 'arrow', 'size' => 'sm'])</a>
            @endif
        </div>

        @if ($suits)
            <div class="ns-dishes">
                @foreach ($suits as $dish)
                    @include('site.components.dish-card', ['dish' => $dish])
                @endforeach
            </div>
        @else
            <div class="ns-empty ns-suits__invite">
                @include('site.components.chain-tiles', ['pattern' => 'step-4', 'tile' => 40])
                <div>
                    @if ($suits === null && ! auth()->check())
                        <p class="t-body">أنشئ حسابك وأخبرنا عن جسمك وهدفك، لنقترح عليك الوجبات التي تناسبك من كل المطاعم.</p>
                        <a class="ns-btn ns-btn--primary" href="{{ route('site.register') }}">أنشئ حسابك</a>
                    @elseif ($suits === null)
                        <p class="t-body">أكمل ملفك (العمر والطول والوزن والنشاط والهدف) لنقترح عليك الوجبات التي تناسبك.</p>
                        <a class="ns-btn ns-btn--primary" href="{{ route('site.criteria') }}">أكمل ملفك</a>
                    @else
                        <p class="t-body ns-muted">لا وجبات معتمدة تناسب معاييرك الآن. نضيف وجبات جديدة باستمرار.</p>
                        <a class="ns-btn ns-btn--secondary" href="{{ route('site.meals') }}">اعرض كل الوجبات</a>
                    @endif
                </div>
            </div>
        @endif
    </div>
</section>
