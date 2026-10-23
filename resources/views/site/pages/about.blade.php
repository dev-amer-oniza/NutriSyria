@extends('site.layouts.app')

@section('title', 'من نحن')

@section('content')
    @php
        $beliefs = [
            ['title' => 'الإنسان هو نقطة البداية', 'text' => 'كل شيء يبدأ بقرار يأخذه شخص ليهتم بصحته. دورنا أن نعطيه المعرفة الدقيقة التي تساعده يفهم خياراته.'],
            ['title' => 'قرار يقود إلى قرار', 'text' => 'كل اختيار واعٍ قد يقود إلى اختيار آخر، ومع الوقت تتشكل سلسلة تغييرات مترابطة.'],
            ['title' => 'التغيير ليس زيادة دائماً', 'text' => 'قد يكون تقليلاً، أو توازناً، أو تغيير اتجاه. المهم أن يكون مقصوداً، ومبنياً على معلومة صحيحة.'],
        ];
        $team = [
            ['initial' => 'ع', 'name' => 'عامر عنيزة', 'role' => 'Co-Founder & CTO', 'field' => 'هندسة البرمجيات'],
            ['initial' => 'م', 'name' => 'ماريا الناظم', 'role' => 'Graphic Design', 'field' => 'هندسة العمارة'],
        ];
    @endphp

    <section class="ns-section">
        <div class="ns-wrap ns-method__hero">
            <div>
                <p class="ns-eyebrow">من نحن</p>
                <h1 class="t-display">من قرار صغير… يبدأ الأثر</h1>
                <p class="t-lead ns-muted">NutriSyria منصة سورية تعطيك أرقاماً موثوقة عن وجبات المطاعم، وتقول لك بصراحة إن كانت تناسبك أنت. لأن كل تغيير كبير يبدأ بخطوة صغيرة مقصودة.</p>
            </div>
            @include('site.components.chain-tiles', ['pattern' => 'hero', 'tile' => 48, 'class' => 'ns-method__art'])
        </div>
    </section>

    <section class="ns-section ns-section--sand" aria-labelledby="idea-title">
        <div class="ns-wrap">
            <div class="ns-section__head">
                <div>
                    <p class="ns-eyebrow">فكرتنا</p>
                    <h2 class="t-title" id="idea-title">حركة صغيرة مقصودة، تطلق سلسلة من الأثر</h2>
                    <p class="t-lead ns-muted">لهذا ترى في هويتنا مربعاً واحداً بلون مختلف: هو الأصل، ومنه تنطلق بقية القطع.</p>
                </div>
            </div>
            <ol class="ns-about__beliefs">
                @foreach ($beliefs as $i => $belief)
                    <li class="ns-about__belief">
                        @include('site.components.chain-tiles', ['pattern' => 'step-'.($i + 1), 'tile' => 40])
                        <h3 class="ns-feature__title">{{ $belief['title'] }}</h3>
                        <p class="ns-feature__text">{{ $belief['text'] }}</p>
                    </li>
                @endforeach
            </ol>
        </div>
    </section>

    <section class="ns-section" aria-labelledby="team-title">
        <div class="ns-wrap">
            <div class="ns-section__head">
                <div>
                    <p class="ns-eyebrow">الفريق</p>
                    <h2 class="t-title" id="team-title">من يبني NutriSyria</h2>
                </div>
            </div>
            <ul class="ns-about__team">
                @foreach ($team as $person)
                    <li class="ns-person-card">
                        <span class="ns-person-card__mark" aria-hidden="true">{{ $person['initial'] }}</span>
                        <div>
                            <h3 class="ns-person-card__name">{{ $person['name'] }}</h3>
                            <p class="ns-person-card__role ns-latin">{{ $person['role'] }}</p>
                            <p class="ns-person-card__field">{{ $person['field'] }}</p>
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>
    </section>

    <section class="ns-section ns-section--sand" aria-labelledby="events-title">
        <div class="ns-wrap">
            <div class="ns-section__head">
                <div>
                    <p class="ns-eyebrow">عرضنا فكرتنا</p>
                    <h2 class="t-title" id="events-title">قبل أن تصل إليك، مرّت الفكرة على لجان تحكيم</h2>
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

    <section class="ns-section" aria-labelledby="more-title">
        <div class="ns-wrap ns-partners__cta">
            <h2 class="t-title" id="more-title">تعرّف علينا أكثر</h2>
            <div class="ns-book__actions">
                <a class="ns-btn ns-btn--primary" href="{{ route('site.method') }}">كيف نتحقق</a>
                <a class="ns-btn ns-btn--secondary" href="{{ route('site.goals') }}">الأهداف الصحية</a>
                <a class="ns-btn ns-btn--secondary" href="{{ route('site.partners') }}">للمطاعم</a>
            </div>
        </div>
    </section>
@endsection
