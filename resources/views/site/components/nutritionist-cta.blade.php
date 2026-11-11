{{-- Advice to see one of the platform's nutritionists (the owner's wish: first consultation free). Param $reasons (list, optional). --}}
<aside class="ns-advice" aria-labelledby="advice-title">
    <img class="ns-advice__art" src="{{ asset('images/site/nutritionist-cover.svg') }}" alt="" width="64" height="64" loading="lazy">
    <div>
        <h2 class="ns-advice__title" id="advice-title">أرقامك محسوبة بمعادلات عامة. أخصائي التغذية يجعلها لك أنت.</h2>
        @if (! empty($reasons))
            <ul class="ns-advice__reasons">
                @foreach ($reasons as $reason)<li>{{ $reason }}</li>@endforeach
            </ul>
        @endif
        <p class="ns-advice__text">يراجع سجلك الصحي بعد موافقتك، ويحدد معاييرك بدقة، فتُقيَّم كل وجبة على أساسها. <b>الاستشارة الأولى مجانية.</b></p>
        <a class="ns-btn ns-btn--primary ns-btn--sm" href="{{ route('site.nutritionists') }}">اختر أخصائياً</a>
    </div>
</aside>
