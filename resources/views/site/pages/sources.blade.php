@extends('site.layouts.app')

@section('title', 'المصادر')

@section('content')
    <section class="ns-section">
        <div class="ns-wrap ns-method__hero">
            <div>
                <p class="ns-eyebrow">المصادر</p>
                <h1 class="t-display">من أين نأتي بأرقامنا وحدودنا</h1>
                <p class="t-lead ns-muted">أرقام المكوّنات من جداول رسمية، وكل حد طبي نطبّقه عليك له مرجع عالمي منسوخ بنصه. وما لم نوثّقه بعد نقول ذلك صراحةً، ولا ندخله في تقييم وجباتك.</p>
                <nav class="ns-goals__chips" aria-label="أقسام الصفحة">
                    <a class="ns-chip" href="#data">مصادر القيم الغذائية</a>
                    <a class="ns-chip" href="#medical">المراجع الطبية</a>
                    <a class="ns-chip" href="#conditions">الحالات الصحية</a>
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

    <section class="ns-section ns-anchor" id="medical" aria-labelledby="medical-title">
        <div class="ns-wrap">
            <div class="ns-section__head">
                <div>
                    <p class="ns-eyebrow">المراجع الطبية</p>
                    <h2 class="t-title" id="medical-title">كل حد نطبّقه عليك، ومرجعه</h2>
                    <p class="t-lead ns-muted">لكل حد نوع يحدد ما يفعله بتقييم الوجبة:</p>
                </div>
            </div>
            <dl class="ns-ref-legend">
                @foreach ($kinds as $key => [$label, $text])
                    <div><dt><span class="ns-ref-kind ns-ref-kind--{{ $key }}">{{ $label }}</span></dt><dd>{{ $text }}</dd></div>
                @endforeach
            </dl>

            <div class="ns-ref-grid">
                @include('site.components.reference-card', ['card' => $general])
            </div>

            <h3 class="ns-ref-group__title">القوالب الغذائية</h3>
            <p class="t-body ns-muted">القالب يوزّع سعراتك على الكربوهيدرات والبروتين والدهون. القالب غير الموثّق يظهر لك، لكننا نقيّم وجباتك بتوزيع «المتوازن» الموثّق حتى يُوثَّق، إلا إن اختاره لك أخصائيّك.</p>
            @foreach ($templates as $group)
                <h4 class="ns-ref-group__sub">{{ $group['label'] }}</h4>
                <div class="ns-ref-grid">
                    @foreach ($group['cards'] as $card)
                        @include('site.components.reference-card', ['card' => $card])
                    @endforeach
                </div>
            @endforeach
        </div>
    </section>

    <section class="ns-section ns-section--sand ns-anchor" id="conditions" aria-labelledby="conditions-title">
        <div class="ns-wrap">
            <div class="ns-section__head">
                <div>
                    <p class="ns-eyebrow">الحالات الصحية</p>
                    <h2 class="t-title" id="conditions-title">ما تغيّره كل حالة</h2>
                    <p class="t-lead ns-muted">حالة «قيد المراجعة» نقيّم وجباتك معها على احتياجك العام، ونقول لك ذلك في كل تقييم، وننصحك بأخصائي.</p>
                </div>
            </div>
            @foreach ($conditions as $group)
                <h3 class="ns-ref-group__sub">{{ $group['label'] }}</h3>
                <div class="ns-ref-grid">
                    @foreach ($group['cards'] as $card)
                        @include('site.components.reference-card', ['card' => $card])
                    @endforeach
                </div>
            @endforeach
        </div>
    </section>

    <section class="ns-section ns-anchor" id="choices" aria-labelledby="choices-title">
        <div class="ns-wrap ns-method__split">
            <div>
                <p class="ns-eyebrow">بصراحة</p>
                <h2 class="t-title" id="choices-title">اختيارات منّا، لا من مرجع طبي</h2>
                <p class="t-body">هذه قواعد وضعناها لنحوّل المراجع إلى تقييم وجبة واحدة. نعلنها لتعرف أين ينتهي المرجع ويبدأ اختيارنا.</p>
            </div>
            <ul class="ns-method__proxies">
                @foreach ($choices as $choice)<li>{{ $choice }}</li>@endforeach
            </ul>
        </div>
    </section>

    <section class="ns-section ns-section--sand">
        <div class="ns-wrap">
            <p class="ns-ref-disclaimer">هذه المعلومات للتثقيف، ولا تغني عن استشارة طبيب أو أخصائي تغذية. لم يراجعها مختص بعد، ونضيف اسمه هنا حين يراجعها.</p>
        </div>
    </section>
@endsection
