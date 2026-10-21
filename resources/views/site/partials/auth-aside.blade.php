<aside class="ns-auth__aside" aria-label="لماذا تنشئ حساباً">
    @include('site.components.chain-tiles', ['pattern' => 'trust', 'tile' => 56, 'class' => 'ns-auth__art'])

    <div class="ns-auth__story">
        <p class="ns-eyebrow ns-eyebrow--on-pine">لماذا حساب؟</p>
        <h2 class="t-title">الطبق نفسه،<br>لا يناسب الجميع بالقدر نفسه</h2>
        <ul class="ns-auth__people">
            @foreach ([['لرياضي يريد زيادة وزنه', 4], ['لسيدة تريد خفض وزنها', 3], ['لمريض سكري', 2]] as [$who, $level])
                <li><span>{{ $who }}</span><b>{{ config("site.fit_levels.$level") }}</b></li>
            @endforeach
        </ul>
        <p class="ns-auth__note">مثال: صحن مجدّرة، <span class="ns-num">620</span> سعرة و<span class="ns-num">92</span> غ كربوهيدرات.</p>
    </div>
</aside>
