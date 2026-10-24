{{--
    Logo lockup from the business card (identity: Logo): the six-piece diamond mark, then
    **Nutri** (Sora 800) and Syria (Sora 400), always left-to-right. Size it with font-size.
    Params: $onPine (bool) for pine grounds.
--}}
<a @class(['ns-logo', 'ns-logo--on-pine' => $onPine ?? false]) href="{{ route('home') }}" aria-label="NutriSyria، الصفحة الرئيسية">
    <svg class="ns-mark" viewBox="-31 -31 62 62" aria-hidden="true">
        <path class="part" d="M-0.88 -25.33L-0.88 -11.68A2.4 2.4 0 0 1 -1.51 -10.06A8.8 8.8 0 0 1 -15.41 -11.25A4.8 4.8 0 0 1 -14.76 -17.24L-4.98 -27.02A2.4 2.4 0 0 1 -0.88 -25.33Z"/>
        <path class="origin" d="M4.98 -27.02L14.76 -17.24A4.8 4.8 0 0 1 15.41 -11.25A8.8 8.8 0 0 1 1.51 -10.06A2.4 2.4 0 0 1 0.88 -11.68L0.88 -25.33A2.4 2.4 0 0 1 4.98 -27.02Z"/>
        <path class="part" d="M-14.96 -8.06A10.56 10.56 0 0 0 -3.92 -6.26A0.8 0.8 0 0 1 -3.04 -4.96L-15.76 7.76A6 6 0 0 1 -24.24 7.76L-29.45 2.55A3.6 3.6 0 0 1 -29.45 -2.55L-24.25 -7.75A6.8 6.8 0 0 1 -14.96 -8.06Z"/>
        <path class="part" d="M14.96 8.06A10.56 10.56 0 0 0 3.92 6.26A0.8 0.8 0 0 1 3.04 4.96L15.76 -7.76A6 6 0 0 1 24.24 -7.76L29.45 -2.55A3.6 3.6 0 0 1 29.45 2.55L24.25 7.75A6.8 6.8 0 0 1 14.96 8.06Z"/>
        <path class="part" d="M-4.98 27.02L-14.76 17.24A4.8 4.8 0 0 1 -15.41 11.25A8.8 8.8 0 0 1 -1.51 10.06A2.4 2.4 0 0 1 -0.88 11.68L-0.88 25.33A2.4 2.4 0 0 1 -4.98 27.02Z"/>
        <path class="part" d="M0.88 25.33L0.88 11.68A2.4 2.4 0 0 1 1.51 10.06A8.8 8.8 0 0 1 15.41 11.25A4.8 4.8 0 0 1 14.76 17.24L4.98 27.02A2.4 2.4 0 0 1 0.88 25.33Z"/>
    </svg><span class="ns-logo__word"><b>Nutri</b>Syria</span>
</a>
