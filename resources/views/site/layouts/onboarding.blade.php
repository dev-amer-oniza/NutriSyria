{{--
    The account set-up: a step bar, then the step's form. Sections: title, heading, lead, form.
    Data: $step (App\Enums\OnboardingStep).
--}}
@extends('site.layouts.app')

@section('content')
    <section class="ns-onboarding">
        <div class="ns-onboarding__box">
            <ol class="ns-stepper" aria-label="خطوات إعداد الحساب">
                @foreach (\App\Enums\OnboardingStep::cases() as $item)
                    <li @class(['is-done' => $item->number() < $step->number(), 'is-current' => $item === $step])
                        @if ($item === $step) aria-current="step" @endif>
                        <span class="ns-stepper__n ns-num">{{ $item->number() }}</span>
                        <span class="ns-stepper__t">{{ $item->title() }}@if ($item === \App\Enums\OnboardingStep::Health) <small>(اختياري)</small>@endif</span>
                    </li>
                @endforeach
            </ol>

            <h1 class="t-title ns-auth__heading">@yield('heading')</h1>
            <p class="t-body ns-muted ns-auth__lead">@yield('lead')</p>

            @include('site.partials.flash')

            @yield('form')

            <p class="ns-form__aside"><a class="ns-link" href="{{ route('home') }}">أكمل لاحقاً</a></p>
        </div>
    </section>
@endsection
