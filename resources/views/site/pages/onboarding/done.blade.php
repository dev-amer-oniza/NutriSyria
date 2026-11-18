@extends('site.layouts.app')

@section('title', 'احتياجك اليومي')

@section('content')
    <section class="ns-onboarding">
        <div class="ns-onboarding__box ns-onboarding__box--wide">
            @if ($sheet)
                @php
                    $kcal = $sheet->get('energy_kcal');
                    $meal = (int) round($sheet->perMeal($kcal));
                @endphp
                <p class="ns-eyebrow">احتياجك اليومي</p>
                <div class="ns-needs">
                    <div class="ns-needs__energy">
                        <span class="ns-num t-figure-xl">{{ number_format($kcal->value) }}</span>
                        <span class="ns-energy__unit">سعرة في اليوم</span>
                    </div>
                    @if ($sheet->meal)
                        <p class="ns-needs__meal">غداؤك في خطة أخصائيّك: <b class="ns-num">{{ number_format($meal) }}</b> سعرة (<span class="ns-num">{{ (int) round($sheet->mealShare * 100) }}%</span> من يومك). <a class="ns-link" href="{{ route('site.criteria') }}#plan">خطتك كاملة</a></p>
                    @else
                        <p class="ns-needs__meal">حصة الوجبة الرئيسية: <b class="ns-num">{{ number_format($meal) }}</b> سعرة (<span class="ns-num">{{ (int) ($sheet->mealShare * 100) }}%</span> من يومك).</p>
                    @endif

                    @if ($bmi)
                        @include('site.components.bmi-card', ['bmi' => $bmi, 'goalUrl' => $goalUrl])
                    @endif

                    @include('site.components.template-card', ['sheet' => $sheet])

                    <dl class="ns-needs__grid">
                        @foreach (['fiber_g', 'sodium_mg', 'sugars_g', 'fat_sat_g'] as $key)
                            @php $c = $sheet->get($key); @endphp
                            <div>
                                <dt>{{ $c->label }} <small>{{ config("nutrition_criteria.criteria.{$key}.info_only") ? 'للمعلومة' : ['min' => 'على الأقل', 'max' => 'حتى', 'target' => ''][$c->bound] }}</small></dt>
                                <dd><span class="ns-num">{{ number_format($c->value) }}</span> {{ $c->unit }}</dd>
                            </div>
                        @endforeach
                    </dl>

                    <div class="ns-needs__how">
                        <h2 class="t-label">كيف حسبناها؟</h2>
                        @include('site.components.energy-steps', ['energy' => $sheet->energy])
                    </div>
                </div>

                @include('site.components.nutritionist-cta')

                <div class="ns-form__row ns-needs__actions">
                    <a class="ns-btn ns-btn--secondary" href="{{ route('site.criteria') }}">كل معاييري</a>
                    <a class="ns-btn ns-btn--primary" href="{{ route('home') }}">اعرض الوجبات</a>
                </div>
            @else
                <h1 class="t-title ns-auth__heading">شكراً، حُفظ ملفك</h1>
                <p class="t-body ns-muted">الحساب الشخصي للاحتياج اليومي متاح من عمر <span class="ns-num">{{ config('nutrition_criteria.min_age') }}</span> سنة، لأن المعادلات مخصصة للبالغين. يمكنك تصفّح الوجبات وقيمها، ونتمنى أن تستشير أخصائي تغذية لما يناسب عمرك.</p>
                @include('site.components.nutritionist-cta')
                <a class="ns-btn ns-btn--primary" href="{{ route('home') }}">اعرض الوجبات</a>
            @endif
        </div>
    </section>
@endsection
