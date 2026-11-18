@extends('site.layouts.app')

@section('title', 'المصادر')

@section('content')
    <section class="ns-section">
        <div class="ns-wrap ns-method__hero">
            <div>
                <p class="ns-eyebrow">المصادر</p>
                <h1 class="t-display">من أين نأتي بأرقامنا وحدودنا</h1>
                <p class="t-lead ns-muted">أرقام المكوّنات من جداول رسمية، وما وضعناه نحن من قواعد نعلنه هنا بصراحة.</p>
                <nav class="ns-goals__chips" aria-label="أقسام الصفحة">
                    <a class="ns-chip" href="#data">مصادر القيم الغذائية</a>
                    <a class="ns-chip" href="#choices">اختيارات منّا</a>
                </nav>
            </div>
            <img class="ns-method__art ns-figure ns-figure--pine" src="{{ asset('images/site/trust-figure.svg') }}" alt="" width="360" height="420" loading="lazy">
        </div>
    </section>

    <section class="ns-section ns-section--sand ns-anchor" id="data" aria-labelledby="data-title">
        <div class="ns-wrap">
            <div class="ns-section__head">
                <div>
                    <p class="ns-eyebrow">القيم الغذائية</p>
                    <h2 class="t-title" id="data-title">من أين تأتي أرقام المكوّنات</h2>
                    <p class="t-lead ns-muted">حين يوجد للغذاء أكثر من مصدر، نستعمل الأحدث والأدق أولاً. <a class="ns-link" href="{{ route('site.method') }}">كيف نتحقق من الحساب</a></p>
                </div>
            </div>
            @if ($sources)
                <ul class="ns-method__sources">
                    @foreach ($sources as $source)
                        <li class="ns-method__source">
                            <h3 class="ns-feature__title">{{ $source['name'] }}</h3>
                            @if ($source['text'])<p class="ns-feature__text">{{ $source['text'] }}</p>@endif
                            <p class="ns-method__count"><span class="ns-num">{{ number_format($source['foods']) }}</span> غذاءً معتمداً</p>
                        </li>
                    @endforeach
                </ul>
                <p class="ns-method__total">في قاعدتنا الآن <span class="ns-num">{{ number_format($foods) }}</span> غذاءً معتمداً.</p>
            @else
                <p class="t-body ns-muted">نراجع الآن أول دفعة من الأغذية، وتظهر أعدادها هنا بعد اعتمادها.</p>
            @endif
        </div>
    </section>

    <section class="ns-section ns-anchor" id="choices" aria-labelledby="choices-title">
        <div class="ns-wrap ns-method__split">
            <div>
                <p class="ns-eyebrow">بصراحة</p>
                <h2 class="t-title" id="choices-title">اختيارات منّا، لا من مرجع طبي</h2>
                <p class="t-body">هذه قواعد وضعناها لنحوّل الاحتياج اليومي إلى تقييم وجبة واحدة. نعلنها لتعرف أين ينتهي المرجع ويبدأ اختيارنا.</p>
            </div>
            <ul class="ns-method__proxies">
                @foreach ($choices as $choice)<li>{{ $choice }}</li>@endforeach
            </ul>
        </div>
    </section>

    <section class="ns-section ns-section--sand">
        <div class="ns-wrap">
            <p class="ns-ref-disclaimer">هذه المعلومات للتثقيف، ولا تغني عن استشارة طبيب أو أخصائي تغذية. NutriSyria حالياً للبالغين الأصحاء ومن يمارسون الرياضة، لا للحوامل ولا لأصحاب الحالات الصحية.</p>
        </div>
    </section>
@endsection
