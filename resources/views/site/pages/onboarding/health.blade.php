@extends('site.layouts.onboarding')

@section('title', 'صحتي')
@section('heading', 'صحتك')
@section('lead', 'اختياري. نستعمله لننبّهك حين لا تناسب وجبةٌ حالتك أو حساسيتك.')

@section('form')
    @php
        $checkedConditions = old('conditions', $profile->health_conditions ?? []);
        $checkedAllergies = old('allergies', $profile->allergies ?? []);
    @endphp
    <form class="ns-form" method="post" action="{{ route('site.onboarding.health') }}" novalidate
          x-data="{ any: @js((bool) ($checkedConditions || $checkedAllergies)) }"
          @change="any = $el.querySelectorAll('input[name$=\'[]\']:checked').length > 0">
        @csrf
        @include('site.components.form.choice-cards', [
            'name' => 'conditions', 'legend' => 'حالات صحية', 'multiple' => true, 'selected' => $checkedConditions,
            'options' => collect($options['conditions'])->mapWithKeys(fn ($c) => [$c->value => ['label' => $c->label()]])->all(),
        ])
        @include('site.components.form.choice-cards', [
            'name' => 'conditions', 'legend' => 'الهضم والدسم', 'multiple' => true, 'selected' => $checkedConditions, 'showErrors' => false,
            'hint' => 'نخفّض لك حد الدهون في اليوم وفي الوجبة الواحدة.',
            'options' => collect($options['digestion'])->mapWithKeys(fn ($c) => [$c->value => ['label' => $c->label()]])->all(),
        ])
        @include('site.components.form.choice-cards', [
            'name' => 'allergies', 'legend' => 'حساسيات', 'multiple' => true, 'cols' => 4, 'selected' => $checkedAllergies,
            'options' => collect($options['allergens'])->mapWithKeys(fn ($a) => [$a->value => ['label' => $a->label()]])->all(),
        ])

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
