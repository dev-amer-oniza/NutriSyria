@extends('site.layouts.app')

@section('title', 'من نحن')

@section('content')
    @php
        // The owner's text, in his sections and order. Each section: number, title, English title.
        $offers = [
            ['icon' => 'target', 'title' => 'للأفراد', 'text' => 'معلومات غذائية تساعدهم على فهم وجباتهم واختيار ما يناسب احتياجاتهم وأهدافهم، مع إمكانية استكشاف المطاعم والمنتجات، وتعديل مقادير الوصفات السورية، وحجز الاستشارات الغذائية.'],
            ['icon' => 'heart', 'title' => 'لاختصاصيي التغذية', 'text' => 'مرجع غذائي وأدوات متخصصة لإعداد الخطط الغذائية، وإدارة الاستشارات، ومتابعة المستفيدين.'],
            ['icon' => 'bowl', 'title' => 'للمطاعم والعلامات التجارية', 'text' => 'أدوات لتحليل القيمة الغذائية للوجبات والمنتجات وفق مكوناتها وكمياتها، وإرسال الوصفات للمراجعة المتخصصة قبل اعتمادها ونشرها.'],
        ];
        $team = [
            ['initial' => 'ع', 'name' => 'عامر عنيزه', 'role' => 'Co-Founder & CTO', 'role_ar' => 'الشريك المؤسس والمدير التقني', 'field' => 'مهندس برمجيات'],
            ['initial' => 'م', 'name' => 'ماريا نظام', 'role' => 'Art Director', 'role_ar' => 'المديرة الفنية', 'field' => 'مهندسة عمارة'],
            ['initial' => 'ك', 'name' => 'كاتيا سليمان', 'role' => 'Nutrition Data Reviewer', 'role_ar' => 'مراجعة البيانات الغذائية', 'field' => 'اختصاصية تغذية'],
        ];
    @endphp

    <section class="ns-section ns-about__sec" aria-labelledby="about-who">
        <div class="ns-wrap ns-about__grid">
            @include('site.components.about-head', ['no' => '01', 'title' => 'من نحن', 'en' => 'Who We Are', 'id' => 'about-who'])
            <div class="ns-about__prose">
                <h1 class="t-display ns-about__lead">مرجعك الغذائي الأول في سوريا</h1>
                <p>لم نبدأ NutriSyria بشاشة تعرض السعرات الحرارية. بدأنا من السؤال الذي يسبقها: من أين تأتي هذه الأرقام؟</p>
                <p>فمعرفة القيمة الغذائية لوجبة لا تتوقف عند الرقم الظاهر أمامنا، بل تبدأ من مصدره، ومدى ارتباطه بالطعام الذي نتناوله فعلًا.</p>
                <p>من هنا جاءت NutriSyria، منصة سورية تعمل على بناء مرجع رقمي للأغذية، يستند إلى مصادر علمية عالمية وإقليمية ومراجعة متخصصة، ويراعي طبيعة الأغذية والوصفات السورية.</p>
                <p>ومن هذا المرجع، نطوّر خدمات تجمع الأفراد واختصاصيي التغذية والمطاعم والعلامات التجارية، لتصبح المعلومات الغذائية أكثر ارتباطًا بوجباتنا اليومية، وتساعد كل شخص على فهم ما يتناوله واختيار ما يناسب احتياجاته وأهدافه الصحية.</p>
            </div>
        </div>
    </section>

    <section class="ns-section ns-section--sand ns-about__sec" aria-labelledby="about-story">
        <div class="ns-wrap">
            <div class="ns-about__grid">
                @include('site.components.about-head', ['no' => '02', 'title' => 'قصتنا', 'en' => 'Our Story', 'id' => 'about-story'])
                <div class="ns-about__prose">
                    <p>بحثنا عن مرجع غذائي موثوق يناسب أغذيتنا ووصفاتنا السورية، لكننا وجدنا البيانات موزّعة بين مصادر مختلفة، ولم نجد مرجعًا واحدًا يلبّي احتياجنا.</p>
                    <p class="ns-about__pull">لذلك قررنا أن نبني المرجع الذي كنا نبحث عنه.</p>
                    <p>ومن البداية، أردناه أساسًا لمنصة تخدم الأفراد واختصاصيي التغذية والمطاعم، لا مجرد قاعدة بيانات للأغذية.</p>
                    <p>بدأنا بجمع بيانات تركيب الأغذية من مراجع عالمية وإقليمية، أبرزها <span class="ns-latin">USDA FoodData Central</span> و<span class="ns-latin">EMFID</span>، وطوّرنا نظامًا لمطابقة البيانات بين المصادر. ثم انضم اختصاصيو التغذية لمراجعة كل غذاء قبل اعتماده.</p>
                    <p class="ns-about__figure">اليوم، تضم قاعدة NutriSyria بيانات <b class="ns-num">209</b> أغذية معتمدة.</p>
                    <p>ونواصل توسيع هذا المرجع ليشمل المزيد من الأغذية والوصفات المحلية، مع طموح لدعم بيانات الأغذية السورية مستقبلًا بتحاليل مخبرية فعلية.</p>
                </div>
            </div>
            <div class="ns-about__events">
                @include('site.components.photo', [
                    'src' => 'images/about/safire-tech', 'small' => 640, 'full' => 1280, 'height' => 853,
                    'sizes' => '(max-width: 860px) 100vw, 760px',
                    'alt' => 'زوّار مؤتمر Safire Tech يستمعون إلى عرض المشاريع حول طاولة الحواسيب',
                    'title' => 'من مؤتمر Safire Tech',
                    'caption' => 'تقدّم 160 مشروعاً، وتأهّل 16 منها إلى النهائي، وكان NutriSyria من المشاريع المؤهلة.',
                    'class' => 'ns-photo--wide',
                ])
                @include('site.components.photo', [
                    'src' => 'images/about/reroot', 'small' => 420, 'full' => 750, 'height' => 1333,
                    'sizes' => '(max-width: 860px) 100vw, 360px',
                    'alt' => 'عرض منصة الغذاء الصحي على المنصة في الحفل الختامي لمسابقة ReRoot',
                    'title' => 'من مسابقة ReRoot من JCI',
                    'caption' => 'فرصة لتحليل نموذج العمل وفهمه أعمق، ثم عرض المشروع في الحفل الختامي.',
                    'class' => 'ns-photo--tall',
                ])
            </div>
        </div>
    </section>

    <section class="ns-section ns-about__sec" aria-labelledby="about-purpose">
        <div class="ns-wrap ns-about__grid">
            @include('site.components.about-head', ['no' => '03', 'title' => 'ما نعمل لأجله', 'en' => 'Our Purpose', 'id' => 'about-purpose'])
            <div class="ns-about__prose">
                <p>نعمل على أن تتجاوز فائدة المرجع حدود جمع البيانات، لتصبح معلوماته أساسًا يمكن الاعتماد عليه في اختيار الوجبات، وإعداد الخطط الغذائية، وتطوير المنتجات الغذائية.</p>
                <p>ونطمح إلى أن يترسخ الاعتماد على المعرفة الغذائية في سوريا، وأن يصبح فهم ما نأكله جزءًا من العناية المستمرة بالصحة، لا اهتمامًا يرتبط بالحميات أو المرض وحدهما.</p>
            </div>
        </div>
    </section>

    <section class="ns-section ns-section--sand ns-about__sec" aria-labelledby="about-offer">
        <div class="ns-wrap">
            @include('site.components.about-head', ['no' => '04', 'title' => 'ماذا نقدم؟', 'en' => 'What We Do', 'id' => 'about-offer'])
            <ul class="ns-about__offers">
                @foreach ($offers as $offer)
                    <li class="ns-about__offer">
                        <span class="ns-feature__icon">@include('site.partials.icon', ['name' => $offer['icon']])</span>
                        <h3 class="ns-about__offer-title">{{ $offer['title'] }}</h3>
                        <p>{{ $offer['text'] }}</p>
                    </li>
                @endforeach
            </ul>
        </div>
    </section>

    <section class="ns-section ns-about__sec" aria-labelledby="about-accuracy">
        <div class="ns-wrap ns-about__grid">
            @include('site.components.about-head', ['no' => '05', 'title' => 'الدقة والشفافية', 'en' => 'Accuracy & Transparency', 'id' => 'about-accuracy'])
            <div class="ns-about__prose">
                <p>نعرف أن القيمة الغذائية لا تنفصل عن طبيعة الطعام وطريقة تحضيره. فاختلاف المكونات وكمياتها وحجم الحصة قد يؤثر في النتائج، حتى عندما نتحدث عن الوجبة نفسها.</p>
                <p>لذلك نميّز بين بيانات الأغذية المستمدة من المراجع العلمية، والقيم المحسوبة للوصفات والوجبات، التي تُعدّ تقديرات مبنية على مكوناتها، وليست بديلًا عن التحاليل المخبرية.</p>
                <p>ولتعزيز الشفافية، يظهر اسم اختصاصي التغذية المسؤول عن مراجعة الوصفات المعتمدة لدى المطاعم والعلامات التجارية.</p>
                <p><a class="ns-link" href="{{ route('site.method') }}">كيف نتحقق من الأرقام@include('site.partials.icon', ['name' => 'arrow', 'size' => 'sm'])</a></p>
            </div>
        </div>
    </section>

    <section class="ns-section ns-section--sand ns-about__sec" aria-labelledby="about-team">
        <div class="ns-wrap">
            @include('site.components.about-head', ['no' => '06', 'title' => 'من يبني NutriSyria', 'en' => 'Our Team', 'id' => 'about-team'])
            <ul class="ns-about__team">
                @foreach ($team as $person)
                    <li class="ns-person-card">
                        <span class="ns-person-card__mark" aria-hidden="true">{{ $person['initial'] }}</span>
                        <div>
                            <h3 class="ns-person-card__name">{{ $person['name'] }}</h3>
                            <p class="ns-person-card__role ns-latin">{{ $person['role'] }}</p>
                            <p class="ns-person-card__role-ar">{{ $person['role_ar'] }}</p>
                            <p class="ns-person-card__field">{{ $person['field'] }}</p>
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>
    </section>

    <section class="ns-section ns-about__sec" aria-labelledby="about-join">
        <div class="ns-wrap ns-about__grid">
            @include('site.components.about-head', ['no' => '07', 'title' => 'كن جزءًا من التجربة', 'en' => 'Join Us', 'id' => 'about-join'])
            <div class="ns-about__prose">
                <p>سواء كنت ترغب في فهم غذائك بشكل أفضل، أو كنت اختصاصي تغذية، أو تدير مطعمًا أو علامة تجارية، يمكنك أن تبدأ تجربتك مع NutriSyria.</p>
                <p>تعرّف على الخدمات المتاحة لك، واختر ما يناسب احتياجاتك.</p>
                <div class="ns-book__actions">
                    <a class="ns-btn ns-btn--primary" href="{{ route('site.register') }}">للأفراد</a>
                    <a class="ns-btn ns-btn--secondary" href="{{ route('site.register', ['type' => 'nutritionist']) }}">لاختصاصيي التغذية</a>
                    <a class="ns-btn ns-btn--secondary" href="{{ route('site.partners') }}">للمطاعم والعلامات التجارية</a>
                </div>
            </div>
        </div>
    </section>

    <section class="ns-about__closing" aria-labelledby="about-closing">
        <div class="ns-wrap ns-about__closing-in">
            <h2 class="t-title" id="about-closing">من قرار صغير يبدأ الأثر</h2>
            <p>نؤمن أن الإنسان هو نقطة البداية في كل تغيير.</p>
            <p>قد تبدأ الخطوة بفهم القيمة الغذائية لوجبة، أو اختيار حصة تناسب احتياجاته، أو إعادة النظر في عادة يومية.</p>
            <p>ومع تكرار القرارات الواعية، يمكن أن تتشكل تغييرات تتناسب مع أهداف كل فرد واحتياجاته.</p>
            <p>في NutriSyria، نوفّر المعرفة والأدوات التي تدعم قرارك. أما البداية، فهي منك.</p>
            <a class="ns-btn ns-btn--on-pine" href="{{ route('site.meals') }}">اكتشف NutriSyria وابدأ من خياراتك اليومية@include('site.partials.icon', ['name' => 'arrow', 'size' => 'sm'])</a>
        </div>
    </section>
@endsection
