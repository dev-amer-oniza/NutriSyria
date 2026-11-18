@extends('site.layouts.app')

@section('title', 'معاييري')

@section('content')
    <section class="ns-section">
        <div class="ns-wrap ns-criteria-page">
            <div class="ns-section__head">
                <div>
                    <p class="ns-eyebrow">حسابي</p>
                    <h1 class="t-title">معاييري</h1>
                    <p class="t-lead ns-muted">هذه الأرقام التي نقيّم عليها كل وجبة لك، محسوبة من جسمك ونشاطك وهدفك. لتغييرها عدّل بياناتك.</p>
                </div>
                <div class="ns-criteria-page__links">
                    <a class="ns-link" href="{{ route('site.my-nutritionist') }}">أخصائيّي</a>
                    @unless ($managed)
                        <a class="ns-link" href="{{ route('site.onboarding.show', \App\Enums\OnboardingStep::Body) }}">عدّل بياناتي</a>
                    @endunless
                </div>
            </div>

            @if ($managed)
                <p class="ns-alert ns-alert--ok ns-managed">{{ \App\Http\Middleware\EnsureProfileUnlocked::MESSAGE }}</p>
            @endif

            @unless ($sheet)
                <p class="ns-alert ns-alert--error">الحساب الشخصي متاح من عمر {{ config('nutrition_criteria.min_age') }} سنة.</p>
            @else
                @if ($plan)
                    @include('site.components.plan-card', ['plan' => $plan, 'by' => $planBy])
                @endif

                @if ($bmi)
                    @include('site.components.bmi-card', ['bmi' => $bmi, 'goalUrl' => $managed ? null : route('site.onboarding.show', \App\Enums\OnboardingStep::Activity)])
                @endif

                @include('site.components.template-card', ['sheet' => $sheet])

                <ul class="ns-criteria">
                    @foreach ($sheet->criteria as $key => $c)
                        <li class="ns-criterion">
                            <div class="ns-criterion__main">
                                <h2 class="ns-criterion__name">
                                    {{ $c->label }}
                                    <small>{{ ['min' => 'حد أدنى', 'max' => 'حد أعلى', 'target' => 'الهدف'][$c->bound] }}{{ $c->scope === 'meal' ? ' للوجبة' : ' في اليوم' }}</small>
                                </h2>
                                <p class="ns-criterion__value">
                                    <span class="ns-num">{{ number_format($c->value) }}</span> {{ $c->unit }}
                                    @if ($c->scope === 'day')<span class="ns-criterion__meal">{{ $sheet->meal ? $sheet->meal['name'] : 'الوجبة' }}: <span class="ns-num">{{ number_format(round($sheet->perMeal($c))) }}</span> {{ $c->unit }}</span>@endif
                                </p>
                                <p class="ns-criterion__basis">
                                    <span @class(['ns-source', 'ns-source--'.$c->source->value])>{{ $c->source->label() }}</span>
                                    {{ $c->source === \App\Enums\CriterionSource::Default ? $c->basis : ($c->defaultValue ? 'المحسوب تلقائياً: '.number_format($c->defaultValue).' '.$c->unit : '') }}
                                </p>
                                @if ($c->note)<p class="ns-field__hint">{{ $c->note }}</p>@endif
                            </div>
                        </li>
                    @endforeach
                </ul>

                @unless ($consultation)
                    @include('site.components.nutritionist-cta')
                @endunless
            @endunless

            <section class="ns-health" aria-labelledby="health-title">
                <h2 class="t-heading" id="health-title">بياناتي الصحية</h2>
                @php $allergies = collect($profile->allergies ?? [])->map(fn ($a) => \App\Enums\Allergen::from($a)->label()); @endphp
                @if ($allergies->isEmpty())
                    <p class="ns-muted">لم تحدد حساسيات.
                        @unless ($managed)<a class="ns-link" href="{{ route('site.onboarding.show', \App\Enums\OnboardingStep::Health) }}">أضفها</a>@endunless
                    </p>
                @else
                    <p>الحساسيات: {{ $allergies->join('، ') }}</p>
                    @unless ($managed)
                        <div class="ns-form__row">
                            <a class="ns-link" href="{{ route('site.onboarding.show', \App\Enums\OnboardingStep::Health) }}">تعديل</a>
                            <form method="post" action="{{ route('site.health.destroy') }}" x-data
                                  @submit="if (! confirm('تُحذف حساسياتك نهائياً. متابعة؟')) $event.preventDefault()">
                                @csrf @method('delete')
                                <button class="ns-btn ns-btn--ghost ns-btn--sm" type="submit">احذف بياناتي الصحية</button>
                            </form>
                        </div>
                    @endunless
                @endif
            </section>
        </div>
    </section>
@endsection
