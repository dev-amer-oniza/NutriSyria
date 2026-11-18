@extends('site.layouts.onboarding')

@section('title', 'صحتي')
@section('heading', 'صحتك')
@section('lead', 'اختياري. إن كان عندك حساسية من طعام، نخبرك بكل وجبة فيها ما يسبّبها لك.')

@section('form')
    @php
        $checkedAllergies = old('allergies', $profile->allergies ?? []);
    @endphp
    <form class="ns-form" method="post" action="{{ route('site.onboarding.health') }}" novalidate
          x-data="{ any: @js((bool) $checkedAllergies) }"
          @change="any = $el.querySelectorAll('input[name$=\'[]\']:checked').length > 0">
        @csrf
        @include('site.components.form.choice-cards', [
            'name' => 'allergies', 'legend' => 'حساسيات', 'multiple' => true, 'cols' => 4, 'selected' => $checkedAllergies,
            'options' => collect($options['allergens'])->mapWithKeys(fn ($a) => [$a->value => ['label' => $a->label()]])->all(),
        ])

        {{-- The owner's decision: the platform is for healthy adults and people who train, not yet for pregnancy. --}}
        <p class="ns-field__hint">NutriSyria حالياً للبالغين الأصحاء ومن يمارسون الرياضة. إن كنتِ حاملاً أو مرضعة، أو عندك حالة صحية، فاحتياجك يحدده طبيبك أو أخصائي تغذية، لا هذا الحساب.</p>

        <div x-show="any" x-cloak>
            <label class="ns-check ns-consent">
                <input type="checkbox" name="consent" value="1" @checked(old('consent', $profile->health_consent_at))>
                <span>أوافق على أن تحفظ NutriSyria هذه المعلومات لتعطيني نتائج تناسبني. تُحفظ مشفّرة، ولا يراها أحد إلا أخصائي أختاره أنا، ويمكنني حذفها متى شئت.</span>
            </label>
            @error('consent')<p class="ns-field__error">{{ $message }}</p>@enderror
        </div>

        <div class="ns-form__row">
            <a class="ns-btn ns-btn--ghost" href="{{ route('site.onboarding.show', \App\Enums\OnboardingStep::Activity) }}">السابق</a>
            <button class="ns-btn ns-btn--primary" type="submit" x-text="any ? 'احسب احتياجي' : 'تخطَّ واحسب احتياجي'">احسب احتياجي</button>
        </div>
    </form>
@endsection
