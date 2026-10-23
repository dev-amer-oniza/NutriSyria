@extends('site.layouts.app')

@section('title', $meal->name)

@section('content')
    @php
        $value = fn (string $code) => $meal->amount($code) !== null ? \App\Services\Nutrition\Fit\MealFitCalculator::number($meal->amount($code)) : '—';
        $grams = fn (string $code) => (int) round($meal->amount($code) ?? 0);
        $more = ['fat_sat_g' => ['الدهون المشبعة', 'غ'], 'sugars_g' => ['السكريات الكلية', 'غ'], 'sodium_mg' => ['الصوديوم', 'ملغ'], 'iron_mg' => ['الحديد', 'ملغ'], 'calcium_mg' => ['الكالسيوم', 'ملغ']];
        $allergens = collect($meal->allergens)->map(fn ($a) => \App\Enums\Allergen::tryFrom($a)?->label())->filter();
    @endphp

    <section class="ns-section">
        <div class="ns-wrap ns-meal">
            <div class="ns-meal__main">
                <a class="ns-link" href="{{ route('site.meals') }}">كل الوجبات</a>

                <div class="ns-meal__head">
                    <div class="ns-meal__cover">
                        @if ($meal->image)
                            <img src="{{ $meal->image }}" alt="{{ $meal->name }}، {{ $meal->brand }}">
                        @else
                            @include('site.components.chain-tiles', ['pattern' => \App\Support\Site\ChainPatterns::dishCover($meal->id), 'tile' => 40])
                        @endif
                    </div>
                    <div>
                        <p class="ns-eyebrow">@if ($meal->brandUrl())<a class="ns-brand-link" href="{{ $meal->brandUrl() }}">{{ $meal->brand }}</a>@else{{ $meal->brand }}@endif</p>
                        <h1 class="t-title">{{ $meal->name }}</h1>
                        @if ($meal->description)<p class="t-body ns-muted">{{ $meal->description }}</p>@endif
                        <p class="ns-meal__verify">
                            @include('site.components.verify-badge', ['level' => $meal->verification])
                            <span>{{ config("site.verification.{$meal->verification}.text") }}</span>
                        </p>
                    </div>
                </div>

                <section class="ns-meal__values" aria-labelledby="values-title">
                    <h2 class="t-heading" id="values-title">
                        @if ($meal->servingGrams)
                            القيم لحصة <span class="ns-num">{{ round($meal->servingGrams) }}</span> غ
                        @else
                            القيم لكل <span class="ns-num">100</span> غ <small class="ns-muted">(لا نعرف وزن الحصة بعد)</small>
                        @endif
                    </h2>
                    <div class="ns-energy__fig">
                        <span class="ns-num t-figure-xl">{{ $value('energy_kcal') }}</span>
                        <span class="ns-energy__unit">سعرة حرارية</span>
                    </div>
                    @include('site.components.macro-bar', ['protein' => $grams('protein_g'), 'carbs' => $grams('carbohydrate_g'), 'fat' => $grams('fat_g')])
                    <div class="ns-panel__stats">
                        @include('site.components.nutrient-stat', ['label' => 'بروتين', 'value' => $value('protein_g'), 'key' => 'protein'])
                        @include('site.components.nutrient-stat', ['label' => 'كربوهيدرات', 'value' => $value('carbohydrate_g'), 'key' => 'carbs'])
                        @include('site.components.nutrient-stat', ['label' => 'دهون', 'value' => $value('fat_g'), 'key' => 'fat'])
                        @include('site.components.nutrient-stat', ['label' => 'ألياف', 'value' => $value('fiber_g')])
                    </div>
                    <dl class="ns-meal__more">
                        @foreach ($more as $code => [$label, $unit])
                            <div><dt>{{ $label }}</dt><dd><span class="ns-num">{{ $value($code) }}</span> {{ $meal->amount($code) !== null ? $unit : '' }}</dd></div>
                        @endforeach
                    </dl>
                </section>

                @if ($meal->ingredients)
                    <section aria-labelledby="ingredients-title">
                        <h2 class="t-heading" id="ingredients-title">المكوّنات</h2>
                        <p class="t-body">{{ implode('، ', $meal->ingredients) }}</p>
                        @if ($allergens->isNotEmpty())
                            <p class="ns-meal__allergens">يحتوي على: {{ $allergens->join('، ') }}.</p>
                        @elseif (! $meal->allergensChecked)
                            <p class="ns-field__hint">لم نتحقق بعد من مسبّبات الحساسية في كل مكوّناتها.</p>
                        @else
                            <p class="ns-field__hint">لا تحتوي على مسبّبات الحساسية الشائعة التي نتابعها.</p>
                        @endif
                    </section>
                @endif
            </div>

            <aside class="ns-meal__fit" aria-labelledby="fit-title">
                <h2 class="ns-advice__title" id="fit-title">مدى مناسبتها لك</h2>
                @if ($fit)
                    <div class="ns-fit">
                        @include('site.components.fit-result', [
                            'level' => $fit->level, 'reasons' => $fit->reasons, 'notes' => $fit->notes,
                            'share' => $fit->share, 'portion' => $fit->portion, 'who' => $fit->who(),
                        ])
                    </div>
                    <a class="ns-link" href="{{ route('site.criteria') }}">معاييري</a>
                @elseif (auth()->check())
                    <p class="t-body">أكمل بياناتك (العمر والطول والوزن والنشاط والهدف) لنقيّم كل وجبة على جسمك وهدفك.</p>
                    <a class="ns-btn ns-btn--primary" href="{{ route('site.criteria') }}">أكمل ملفك</a>
                @else
                    <p class="t-body">سجّل الدخول، وأخبرنا عن جسمك وهدفك وحالتك، لنقول لك إن كانت تناسبك وما الحصة المناسبة لك.</p>
                    <div class="ns-book__actions">
                        <a class="ns-btn ns-btn--primary" href="{{ route('site.register') }}">أنشئ حسابك</a>
                        <a class="ns-btn ns-btn--ghost" href="{{ route('site.login') }}">سجّل الدخول</a>
                    </div>
                @endif
            </aside>
        </div>
    </section>
@endsection
