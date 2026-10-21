@extends('site.layouts.onboarding')

@section('title', 'نشاطي وهدفي')
@section('heading', 'نشاطك وهدفك')
@section('lead', 'اختر الوصف الأقرب إلى أسبوعك المعتاد، لا إلى أفضل أسابيعك.')

@section('form')
    <form class="ns-form" method="post" action="{{ route('site.onboarding.activity') }}" novalidate>
        @csrf
        @include('site.components.form.choice-cards', [
            'name' => 'activity_level', 'legend' => 'نشاطك اليومي', 'cols' => 1, 'selected' => $profile->activity_level?->value,
            'options' => collect($options['levels'])->mapWithKeys(fn ($l) => [$l->value => ['label' => $l->label(), 'hint' => $l->description()]])->all(),
        ])
        @include('site.components.form.choice-cards', [
            'name' => 'goal', 'legend' => 'هدفك', 'selected' => $profile->goal?->value,
            'options' => collect($options['goals'])->mapWithKeys(fn ($g) => [$g->value => ['label' => $g->label()]])->all(),
        ])
        <div class="ns-form__row">
            <a class="ns-btn ns-btn--ghost" href="{{ route('site.onboarding.show', \App\Enums\OnboardingStep::Body) }}">السابق</a>
            <button class="ns-btn ns-btn--primary" type="submit">التالي</button>
        </div>
    </form>
@endsection
