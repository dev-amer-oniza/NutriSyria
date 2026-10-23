@extends('site.layouts.app')

@section('title', 'للمطاعم')

@section('content')
    @php
        $benefits = [
            ['icon' => 'document', 'title' => 'صفحة لمطعمك', 'text' => 'شعارك وعنوانك وساعات دوامك والتوصيل وطرق التواصل معك، ووجباتك كلها في مكان واحد.'],
            ['icon' => 'chart', 'title' => 'أرقام موثوقة لكل وجبة', 'text' => 'نحسب قيم كل حصة من مكوّنات موثّقة المصدر، ويراجعها أخصائي تغذية ويعتمدها.'],
            ['icon' => 'target', 'title' => 'زبائن يبحثون عنك', 'text' => 'من يتابع السكري أو الضغط أو وزنه يرى وجباتك التي تناسبه، فيطلب وهو مطمئن.'],
            ['icon' => 'compare', 'title' => 'ثقة تبنيها', 'text' => 'كل رقم يظهر مع مستوى التحقق منه، فيعرف الزبون أنك لا تقدّر بالتقريب.'],
        ];
        $needs = [
            'الوصفة بمكوّناتها وكمياتها، كما تُحضَّر في مطبخك.',
            'وزن الطبخة بعد الطبخ، وعدد الحصص التي تخرج منها.',
            'صور الوجبات وشعار المطعم (اختياري، تظهر بدلها رسوم المنصة).',
            'العنوان وساعات الدوام والتوصيل وأرقام التواصل.',
        ];
        $faq = [
            ['q' => 'هل تظهر وصفتي للناس؟', 'a' => 'الكميات لا تظهر على الموقع أبداً: يراها فقط أخصائي التغذية الذي يراجعها وإدارة المنصة. نعرض أسماء المكوّنات ومسبّبات الحساسية فقط، لأن من عنده حساسية يحتاجها.'],
            ['q' => 'متى تظهر وجباتي؟', 'a' => 'كل وجبة تظهر بمجرد أن يعتمد أخصائي التغذية قيمها، دون انتظار بقية المنيو. وما لم يُعتمد بعد يظهر في صفحتك باسمه تحت "قيمها قيد المراجعة".'],
            ['q' => 'هل أستطيع تعديل وصفة بعد اعتمادها؟', 'a' => 'نعم، من لوحتك. لكن الوجبة تختفي من الموقع حتى يعيد الأخصائي اعتماد قيمها، كي لا تظهر أرقام لا تطابق الطبق.'],
            ['q' => 'ماذا لو استعملت مكوّناً لا تتوفر بياناته؟', 'a' => 'نأخذ أقرب غذاء موثّق له ونعلّم الوجبة "تقديري" بوضوح. وإن قدّمت تحليلاً مخبرياً للطبق نفسه صارت "موثّق".'],
            ['q' => 'عندي أكثر من مطعم أو فرع؟', 'a' => 'يمكن لحساب واحد إدارة أكثر من مطعم، لكل واحد صفحته ومنيوه.'],
        ];
    @endphp

    <section class="ns-section">
        <div class="ns-wrap ns-partners__hero">
            <div>
                <p class="ns-eyebrow">للمطاعم</p>
                <h1 class="t-display">قيم غذائية موثّقة لوجباتك، يراجعها أخصائي تغذية</h1>
                <p class="t-lead ns-muted">أرسل وصفاتك، ونحسب قيمة كل حصة من مصادر موثّقة، ويعتمدها أخصائي التغذية، فتظهر وجباتك لمن يبحث عن طعام يناسبه.</p>
                <div class="ns-book__actions">
                    <a class="ns-btn ns-btn--primary" href="{{ $joinUrl }}">سجّل مطعمك</a>
                    @if ($example)
                        <a class="ns-btn ns-btn--ghost" href="{{ route('site.brands.show', $example->id) }}">شاهد صفحة مطعم</a>
                    @endif
                </div>
            </div>
            @include('site.components.chain-tiles', ['pattern' => 'hero', 'tile' => 56, 'class' => 'ns-partners__art'])
        </div>
    </section>

    @if ($stats)
        <section class="ns-partners__stats" aria-label="المنصة بالأرقام">
            <div class="ns-wrap">
                <p><span class="ns-num">{{ $stats['restaurants'] }}</span> مطعماً</p>
                <p><span class="ns-num">{{ $stats['meals'] }}</span> وجبة بقيم معتمدة</p>
            </div>
        </section>
    @endif

    <section class="ns-section" aria-labelledby="benefits-title">
        <div class="ns-wrap">
            <div class="ns-section__head">
                <div>
                    <p class="ns-eyebrow">ماذا تكسب</p>
                    <h2 class="t-title" id="benefits-title">مطعمك، بأرقام يثق بها زبائنك</h2>
                </div>
            </div>
            <ul class="ns-partners__benefits">
                @foreach ($benefits as $benefit)
                    <li class="ns-partners__benefit">
                        <span class="ns-feature__icon">@include('site.partials.icon', ['name' => $benefit['icon']])</span>
                        <h3 class="ns-feature__title">{{ $benefit['title'] }}</h3>
                        <p class="ns-feature__text">{{ $benefit['text'] }}</p>
                    </li>
                @endforeach
            </ul>
        </div>
    </section>

    @include('site.components.steps', [
        'id' => 'partner-steps-title',
        'eyebrow' => 'كيف ننضم',
        'title' => 'من مطبخك إلى صفحتك، في أربع خطوات',
        'lead' => 'أنت تعرف وصفتك، ونحن نحسب. أخصائي التغذية يراجع ويعتمد قبل أن يظهر أي رقم.',
        'steps' => [
            ['title' => 'سجّل مطعمك', 'text' => 'أنشئ حسابك بصفتك صاحب مطعم، فنتواصل معك على واتساب ونفعّل لوحتك.'],
            ['title' => 'أضف وصفاتك', 'text' => 'من لوحتك: المكوّنات وكمياتها، ووزن الطبخة بعد الطبخ، وعدد الحصص.'],
            ['title' => 'يراجعها الأخصائي', 'text' => 'يراجع أخصائي التغذية الحساب، وقد يرسل لك ملاحظة لتعدّل، ثم يعتمد القيم.'],
            ['title' => 'تظهر وجباتك', 'text' => 'في صفحة مطعمك وفي الموقع، ومع كل وجبة مدى مناسبتها لكل زبون.'],
        ],
    ])

    @include('site.sections.home.trust', [
        'eyebrow' => 'مستويات التحقق',
        'title' => 'كل وجبة تظهر بمستوى التحقق منها',
        'lead' => 'نقول للزبون بوضوح من أين جاء كل رقم.',
        'note' => 'إن قدّمت تحليلاً مخبرياً لطبقك، تظهر قيمه "موثّق"، أعلى مستوى ثقة.',
    ])

    <section class="ns-section" aria-labelledby="needs-title">
        <div class="ns-wrap ns-partners__split">
            <div>
                <p class="ns-eyebrow">ما نحتاجه منك</p>
                <h2 class="t-title" id="needs-title">قليل، وتعرفه أصلاً</h2>
                <ul class="ns-partners__needs">
                    @foreach ($needs as $need)
                        <li>{{ $need }}</li>
                    @endforeach
                </ul>
            </div>
            <aside class="ns-partners__privacy" aria-labelledby="privacy-title">
                <h3 class="ns-advice__title" id="privacy-title">وصفتك تبقى لك</h3>
                <p class="t-body">الكميات لا تظهر على الموقع أبداً. يراها فقط أخصائي التغذية الذي يراجعها وإدارة المنصة. نعرض أسماء المكوّنات ومسبّبات الحساسية فقط، لأن من عنده حساسية يحتاجها.</p>
            </aside>
        </div>
    </section>

    <section class="ns-section ns-section--sand" aria-labelledby="faq-title">
        <div class="ns-wrap ns-partners__faq">
            <h2 class="t-title" id="faq-title">أسئلة شائعة</h2>
            @foreach ($faq as $item)
                <details class="ns-faq">
                    <summary>{{ $item['q'] }}</summary>
                    <p>{{ $item['a'] }}</p>
                </details>
            @endforeach
        </div>
    </section>

    <section class="ns-section">
        <div class="ns-wrap ns-partners__cta">
            <h2 class="t-title">جاهز تعرض وجباتك بأرقامها؟</h2>
            <a class="ns-btn ns-btn--primary" href="{{ $joinUrl }}">سجّل مطعمك</a>
        </div>
    </section>
@endsection
