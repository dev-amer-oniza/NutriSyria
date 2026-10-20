{{-- Logo lockup. Params: $onPine (bool) for pine grounds. --}}
<a @class(['ns-logo', 'ns-logo--on-pine' => $onPine ?? false]) href="{{ route('home') }}" aria-label="NutriSyria، الصفحة الرئيسية">
    <svg class="ns-mark" viewBox="0 0 64 64" aria-hidden="true">
        <path class="petal" d="M2 30A28 28 0 0 1 30 2A28 28 0 0 1 2 30Z"/>
        <path class="petal" d="M34 2A28 28 0 0 1 62 30A28 28 0 0 1 34 2Z"/>
        <path class="petal" d="M34 62A28 28 0 0 1 62 34A28 28 0 0 1 34 62Z"/>
        <path class="origin" d="M2 62A28 28 0 0 1 30 34A28 28 0 0 1 2 62Z"/>
    </svg>
    <span class="ns-logo__word">NutriSyria</span>
</a>
