{{-- Param $dishes: list of meal cards (site.components.dish-card). --}}
<section class="ns-section" aria-labelledby="dishes-title">
    <div class="ns-wrap">
        <div class="ns-section__head">
            <div>
                <p class="ns-eyebrow">من مطاعمنا</p>
                <h2 class="t-title" id="dishes-title">وجبات نعرفها، بأرقام نعرف مصدرها</h2>
                <p class="t-lead ns-muted">القيم لكل حصة، ومع كل وجبة مستوى التحقق من أرقامها.</p>
            </div>
            <a class="ns-link" href="{{ route('site.meals') }}">كل الوجبات@include('site.partials.icon', ['name' => 'arrow', 'size' => 'sm'])</a>
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
                <p class="t-body ns-muted">قريباً تظهر هنا وجبات المطاعم بعد أن يراجع أخصائي التغذية قيمها ويعتمدها.</p>
            </div>
        @endif
    </div>
</section>
