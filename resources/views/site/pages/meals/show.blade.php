@extends('site.layouts.app')

@section('title', $meal->name)

@section('content')
    @php
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
                            <span>{{ $meal->verificationText() }}</span>
                        </p>
                    </div>
                </div>

                @include('site.components.meal-values', ['meal' => $meal])
                @if ($meal->platform)
                    <a class="ns-btn ns-btn--ghost ns-meal__mine" href="{{ route('site.kitchen.recipe', $meal->id) }}">اطبخها على طريقتك: غيّر المقادير واعرف قيمها</a>
                @endif

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
