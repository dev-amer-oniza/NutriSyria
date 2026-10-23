@extends('site.layouts.app')

@section('title', 'معاييري')

@section('content')
    <section class="ns-section">
        <div class="ns-wrap ns-criteria-page">
            <div class="ns-section__head">
                <div>
                    <p class="ns-eyebrow">حسابي</p>
                    <h1 class="t-title">معاييري</h1>
                    <p class="t-lead ns-muted">هذه الأرقام التي نقيّم عليها كل وجبة لك. غيّر ما تريد، أو أرجعه إلى المحسوب تلقائياً.</p>
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

                @include('site.components.template-card', ['sheet' => $sheet, 'selectable' => $selectableTemplates, 'locked' => $templateLocked || $managed])

                <ul class="ns-criteria">
                    @foreach (config('nutrition_criteria.criteria') as $key => $definition)
                        @php $c = $sheet->get($key); @endphp
                        <li class="ns-criterion" x-data="{ editing: @js($errors->has('value') && old('_criterion') === $key) }">
                            <div class="ns-criterion__main">
                                <h2 class="ns-criterion__name">
                                    {{ $definition['label'] }}
                                    <small>{{ ['min' => 'حد أدنى', 'max' => 'حد أعلى', 'target' => 'الهدف'][$c?->bound ?? $definition['bound']] }}{{ $definition['scope'] === 'meal' ? ' للوجبة' : ' في اليوم' }}</small>
                                </h2>
                                @if ($c)
                                    <p class="ns-criterion__value">
                                        <span class="ns-num">{{ number_format($c->value) }}</span> {{ $c->unit }}
                                        @if ($c->scope === 'day')<span class="ns-criterion__meal">{{ $sheet->meal ? $sheet->meal['name'] : 'الوجبة' }}: <span class="ns-num">{{ number_format(round($sheet->perMeal($c))) }}</span> {{ $c->unit }}</span>@endif
                                    </p>
                                    <p class="ns-criterion__basis">
                                        <span @class(['ns-source', 'ns-source--'.$c->source->value])>{{ $c->source->label() }}</span>
                                        {{ $c->source === \App\Enums\CriterionSource::Default ? $c->basis : ($c->defaultValue ? 'المحسوب تلقائياً: '.number_format($c->defaultValue).' '.$c->unit : '') }}
                                    </p>
                                    @if ($c->note)<p class="ns-field__hint">{{ $c->note }}</p>@endif
                                @else
                                    <p class="ns-criterion__basis">غير مفعّل لك. يُفعَّل تلقائياً مع بعض الحالات الصحية، أو حدده بنفسك.</p>
                                @endif
                            </div>

                            <div class="ns-criterion__actions">
                                @if ($managed || $c?->source === \App\Enums\CriterionSource::Nutritionist)
                                    <span class="ns-criterion__locked">يغيّره أخصائيّك فقط</span>
                                @else
                                    <button class="ns-btn ns-btn--ghost ns-btn--sm" type="button" x-show="! editing" @click="editing = true">{{ $c ? 'تعديل' : 'تحديد' }}</button>
                                    @if ($c?->source === \App\Enums\CriterionSource::User)
                                        <form method="post" action="{{ route('site.criteria.reset', $key) }}" x-show="! editing">
                                            @csrf @method('delete')
                                            <button class="ns-btn ns-btn--ghost ns-btn--sm" type="submit">إرجاع للمحسوب</button>
                                        </form>
                                    @endif
                                @endif
                            </div>

                            <form class="ns-criterion__edit" method="post" action="{{ route('site.criteria.update', $key) }}" x-show="editing" x-cloak novalidate>
                                @csrf @method('put')
                                <input type="hidden" name="_criterion" value="{{ $key }}">
                                <label class="ns-sr" for="v-{{ $key }}">{{ $definition['label'] }}</label>
                                <input class="ns-input" id="v-{{ $key }}" name="value" inputmode="decimal" dir="ltr"
                                       value="{{ old('_criterion') === $key ? old('value') : ($c?->value) }}"
                                       placeholder="{{ $definition['range'][0] }}–{{ $definition['range'][1] }}">
                                <span class="ns-criterion__unit">{{ $definition['unit'] }}</span>
                                <button class="ns-btn ns-btn--primary ns-btn--sm" type="submit">حفظ</button>
                                <button class="ns-btn ns-btn--ghost ns-btn--sm" type="button" @click="editing = false">إلغاء</button>
                                @if (old('_criterion') === $key)
                                    @error('value')<p class="ns-field__error">{{ $message }}</p>@enderror
                                @endif
                            </form>
                        </li>
                    @endforeach
                </ul>

                @unless ($consultation)
                    @include('site.components.nutritionist-cta', ['reasons' => $sheet->advice])
                @endunless
            @endunless

            <section class="ns-health" aria-labelledby="health-title">
                <h2 class="t-heading" id="health-title">بياناتي الصحية</h2>
                @php
                    $conditions = collect($profile->health_conditions ?? [])->map(fn ($c) => \App\Enums\HealthCondition::from($c)->label());
                    $allergies = collect($profile->allergies ?? [])->map(fn ($a) => \App\Enums\Allergen::from($a)->label());
                @endphp
                @if ($conditions->isEmpty() && $allergies->isEmpty())
                    <p class="ns-muted">لم تحدد حالات صحية أو حساسيات.
                        @unless ($managed)<a class="ns-link" href="{{ route('site.onboarding.show', \App\Enums\OnboardingStep::Health) }}">أضفها</a>@endunless
                    </p>
                @else
                    <p>الحالات: {{ $conditions->join('، ') ?: 'لا شيء' }}</p>
                    <p>الحساسيات: {{ $allergies->join('، ') ?: 'لا شيء' }}</p>
                    @unless ($managed)
                        <div class="ns-form__row">
                            <a class="ns-link" href="{{ route('site.onboarding.show', \App\Enums\OnboardingStep::Health) }}">تعديل</a>
                            <form method="post" action="{{ route('site.health.destroy') }}" x-data
                                  @submit="if (! confirm('تُحذف حالاتك الصحية وحساسياتك نهائياً. متابعة؟')) $event.preventDefault()">
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
