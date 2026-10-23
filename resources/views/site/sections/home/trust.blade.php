{{-- The verification levels on a pine band. Optional params: $eyebrow, $title, $lead, $note (one more line). --}}
<section class="ns-trust" aria-labelledby="trust-title">
    <div class="ns-wrap ns-trust__in">
        <div>
            <p class="ns-eyebrow ns-eyebrow--on-pine">{{ $eyebrow ?? 'الشفافية' }}</p>
            <h2 class="t-title" id="trust-title">{{ $title ?? 'أرقام تعرف مصدرها' }}</h2>
            <p class="t-lead ns-trust__lead">{{ $lead ?? 'كل قيمة في NutriSyria تظهر مع مستوى التحقق منها، لتعرف مقدار الثقة الذي يمكنك أن تضعه فيها.' }}</p>

            <ul class="ns-levels">
                @foreach (config('site.verification') as $level => $v)
                    <li class="ns-level">
                        @include('site.components.verify-badge', ['level' => $level])
                        <p>{{ $v['text'] }}</p>
                    </li>
                @endforeach
            </ul>

            @isset($note)<p class="ns-trust__lead">{{ $note }}</p>@endisset

            <a class="ns-btn ns-btn--on-pine" href="{{ route('site.method') }}">اقرأ منهجية التحقق</a>
        </div>

        @include('site.components.chain-tiles', ['pattern' => 'trust', 'tile' => 72, 'class' => 'ns-trust__art'])
    </div>
</section>
