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

        <div class="ns-dishes">
            @foreach ($dishes as $dish)
                @include('site.components.dish-card', ['dish' => $dish])
            @endforeach
        </div>
    </div>
</section>
