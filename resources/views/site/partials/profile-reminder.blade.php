{{-- A thin strip for a signed-in person whose set-up is not finished. Data: $nextStep (OnboardingStep). --}}
<div class="ns-reminder" role="note">
    <div class="ns-wrap ns-reminder__in">
        <span>أكمل ملفك لنحسب احتياجك، ونقيّم الوجبات لك أنت.</span>
        <a class="ns-link" href="{{ route('site.onboarding.show', $nextStep) }}">أكمل الآن</a>
    </div>
</div>
