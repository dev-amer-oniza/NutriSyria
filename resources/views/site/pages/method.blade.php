@extends('site.layouts.app')

@section('title', 'كيف نتحقق')

@section('content')
    @php
        $journey = [
            ['icon' => 'document', 'title' => 'كل رقم له مصدر', 'text' => 'أرقام المكوّنات من جداول التركيب الغذائي الرسمية لوزارة الزراعة الأمريكية (USDA)، ونأخذ الأحدث أولاً: التحاليل المخبرية الحديثة، ثم المرجع الكلاسيكي. لا نكتب رقماً بلا مصدر، ولا يُستعمل أي سجل قبل أن نراجعه ونعتمده.', 'example' => 'العدس النيء: سجل USDA معروف برقمه، ومعه كل قيمه لكل 100 غ.'],
            ['icon' => 'scale', 'title' => 'من الكمية إلى الغرام', 'text' => 'الوصفة تُكتب بلغة المطبخ: كوب، ملعقة، حبة. نحوّل كل كمية إلى غرامات حسب شكل المكوّن، لأن كوب الحمص النيء غير كوب المسلوق. وإن كان للحبة أكثر من حجم، يختار المطعم الحجم في وصفته.', 'example' => 'كوب الحمص النيء 200 غ، وكوب الحمص المسلوق 164 غ.'],
            ['icon' => 'bowl', 'title' => 'الحساب', 'text' => 'نجمع قيم كل مكوّن حسب وزنه، ثم نقسمها على وزن الطبخة بعد الطبخ وعدد الحصص. المكوّن الذي لا نعرف كيف نحوّله لا نحسبه صفراً: يبقى ظاهراً مع السبب، ولا تُعتمد الوجبة قبل حلّه.', 'example' => 'الطبخة تخسر ماءً أو تشربه، فالوزن بعد الطبخ هو ما يحدد قيمة الحصة.'],
            ['icon' => 'compare', 'title' => 'تكميل الناقص بلا تخمين', 'text' => 'إن نقصت قيمة في مصدر المكوّن، نكمّلها من مصدر معتمد آخر لنفس الغذاء وبنفس شكله، قيمةً بقيمة، بلا متوسطات. ونذكر ما كمّلناه ومن أين.', 'example' => 'الألياف ناقصة في سجل البرغل الحديث؟ نأخذها من سجل البرغل الآخر، لا من القمح.'],
            ['icon' => 'heart', 'title' => 'يعتمدها أخصائي تغذية', 'text' => 'لا يظهر أي رقم على الموقع قبل أن يراجع أخصائي تغذية الحساب ويعتمده، وقد يطلب من المطعم تعديلاً أولاً. وإن عُدّلت الوصفة بعد الاعتماد، تختفي الوجبة حتى يُعاد اعتمادها.', 'example' => 'لذلك قد ترى في صفحة مطعم وجبات "قيمها قيد المراجعة" بلا أرقام.'],
        ];
        $example = [
            ['item' => 'عدس نيء', 'amount' => 'كوبان', 'grams' => 384, 'kcal' => 1352],
            ['item' => 'برغل خشن', 'amount' => 'كوب', 'grams' => 140, 'kcal' => 479],
            ['item' => 'زيت زيتون', 'amount' => '4 ملاعق كبيرة', 'grams' => 54, 'kcal' => 477],
            ['item' => 'بصل', 'amount' => 'حبتان وسط', 'grams' => 220, 'kcal' => 88],
        ];
        $exampleKcal = array_sum(array_column($example, 'kcal'));
        $exampleCooked = 2000;
        $exampleServings = 8;
    @endphp

    <section class="ns-section">
        <div class="ns-wrap ns-method__hero">
            <div>
                <p class="ns-eyebrow">منهجية التحقق</p>
                <h1 class="t-display">كيف نصل إلى رقم تثق به</h1>
                <p class="t-lead ns-muted">من جدول التركيب الغذائي إلى صحنك: من أين يأتي كل رقم، وكيف نحسب الوجبة، ومن يراجعها، وما الذي لا نستطيع أن نعدك به.</p>
            </div>
            @include('site.components.chain-tiles', ['pattern' => 'trust', 'tile' => 56, 'class' => 'ns-method__art'])
        </div>
    </section>

    <section class="ns-section ns-section--sand" aria-labelledby="journey-title">
        <div class="ns-wrap">
            <div class="ns-section__head">
                <div>
                    <p class="ns-eyebrow">رحلة الرقم</p>
                    <h2 class="t-title" id="journey-title">خمس خطوات قبل أن ترى أي قيمة</h2>
                </div>
            </div>
            <ol class="ns-method__journey">
                @foreach ($journey as $i => $step)
                    <li class="ns-method__step">
                        <span class="ns-feature__icon">@include('site.partials.icon', ['name' => $step['icon']])</span>
                        <div>
                            <h3 class="ns-feature__title"><span class="ns-num">{{ sprintf('%02d', $i + 1) }}</span> {{ $step['title'] }}</h3>
                            <p class="ns-feature__text">{{ $step['text'] }}</p>
                            <p class="ns-method__example">مثال: {{ $step['example'] }}</p>
                        </div>
                    </li>
                @endforeach
            </ol>
        </div>
    </section>

    <section class="ns-section" aria-labelledby="example-title">
        <div class="ns-wrap ns-method__split">
            <div>
                <p class="ns-eyebrow">مثال توضيحي</p>
                <h2 class="t-title" id="example-title">طبخة مجدرة، خطوة بخطوة</h2>
                <p class="t-body ns-muted">طبخة من {{ $exampleServings }} حصص. الأرقام مقرّبة وللتوضيح فقط، لا لوجبة مطعم بعينه.</p>

                <div class="ns-method__table-wrap">
                    <table class="ns-method__table">
                        <thead>
                            <tr><th scope="col">المكوّن</th><th scope="col">في الوصفة</th><th scope="col">بالغرام</th><th scope="col">السعرات</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($example as $row)
                                <tr>
                                    <th scope="row">{{ $row['item'] }}</th>
                                    <td>{{ $row['amount'] }}</td>
                                    <td><span class="ns-num">{{ $row['grams'] }}</span> غ</td>
                                    <td><span class="ns-num">{{ number_format($row['kcal']) }}</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr><th scope="row" colspan="3">مجموع الطبخة</th><td><span class="ns-num">{{ number_format($exampleKcal) }}</span></td></tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <ol class="ns-method__sum">
                <li>وزن الطبخة بعد الطبخ <span class="ns-num">{{ number_format($exampleCooked) }}</span> غ، لأن العدس والبرغل يشربان الماء.</li>
                <li>الحصة = <span class="ns-num">{{ number_format($exampleCooked) }}</span> ÷ <span class="ns-num">{{ $exampleServings }}</span> = <span class="ns-num">{{ $exampleCooked / $exampleServings }}</span> غ.</li>
                <li>سعرات الحصة = <span class="ns-num">{{ number_format($exampleKcal) }}</span> ÷ <span class="ns-num">{{ $exampleServings }}</span> ≈ <strong><span class="ns-num">{{ round($exampleKcal / $exampleServings) }}</span> سعرة</strong>.</li>
                <li>وبنفس الطريقة البروتين والدهون والكربوهيدرات والألياف والصوديوم وغيرها.</li>
            </ol>
        </div>
    </section>

    <section class="ns-section ns-section--sand" aria-labelledby="sources-title">
        <div class="ns-wrap">
            <div class="ns-section__head">
                <div>
                    <p class="ns-eyebrow">المصادر</p>
                    <h2 class="t-title" id="sources-title">من أين تأتي أرقام المكوّنات</h2>
                    <p class="t-lead ns-muted">حين يوجد للغذاء أكثر من مصدر، نستعمل الأحدث والأدق أولاً. وحين تتوفر جداول سورية أو تحاليل مخبرية محلية، تأخذ الأولوية على الجميع.</p>
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
                <p class="ns-method__total">في قاعدتنا الآن <span class="ns-num">{{ number_format($foods) }}</span> غذاءً معتمداً، وكل واحد منها راجعناه قبل استعماله.</p>
            @else
                <p class="t-body ns-muted">نراجع الآن أول دفعة من الأغذية، وتظهر أعدادها هنا بعد اعتمادها.</p>
            @endif
        </div>
    </section>

    <section class="ns-section" aria-labelledby="proxies-title">
        <div class="ns-wrap ns-method__split">
            <div>
                <p class="ns-eyebrow">المقاربات</p>
                <h2 class="t-title" id="proxies-title">حين لا يوجد الغذاء السوري نفسه</h2>
                <p class="t-body">بعض أغذيتنا لا تملك USDA بياناتها، كاللبنة والجبنة البيضاء. نأخذ لها أقرب غذاء موثّق، ونعلّم كل وجبة فيها مقاربة بمستوى "تقديري"، فتعرف ذلك قبل أن تقرأ الأرقام.</p>
            </div>
            @if ($proxies)
                <ul class="ns-method__proxies">
                    @foreach ($proxies as $proxy)
                        <li><strong>{{ $proxy['food'] }}</strong>@if ($proxy['note']): {{ $proxy['note'] }}@endif</li>
                    @endforeach
                </ul>
            @else
                <p class="t-body ns-muted">لا توجد مقاربات معتمدة في قاعدتنا الآن.</p>
            @endif
        </div>
    </section>

    @include('site.sections.home.trust', [
        'eyebrow' => 'مستويات التحقق',
        'title' => 'كل وجبة تظهر بمستوى التحقق منها',
        'lead' => 'المستوى يقول لك مقدار الثقة الذي تضعه في الأرقام.',
        'note' => 'إن نقص لوجبة رقم ما، نكتب "—" ونقول إنه غير متوفر، ولا نخمّنه.',
        'link' => false,
    ])

    <section class="ns-section" aria-labelledby="limits-title">
        <div class="ns-wrap ns-method__split">
            <div>
                <p class="ns-eyebrow">بصراحة</p>
                <h2 class="t-title" id="limits-title">ما لا نستطيع أن نعدك به</h2>
                <ul class="ns-partners__needs">
                    <li>الحساب من الوصفة قريب من الحقيقة، لكنه ليس تحليلاً للطبق نفسه: حجم الحبة، ونوع الزيت، ومدة الطبخ، تفرق من طبخة لأخرى.</li>
                    <li>أغلب الأرقام من جداول أمريكية، والغذاء السوري قد يختلف عنها قليلاً حسب الصنف والتربة والتحضير.</li>
                    <li>نعتمد على أن المطعم يطبخ كما كتب وصفته. حين تتغير الوصفة، نعيد المراجعة.</li>
                </ul>
            </div>
            <aside class="ns-partners__privacy" aria-labelledby="lab-title">
                <h3 class="ns-advice__title" id="lab-title">الأدق: التحليل المخبري</h3>
                <p class="t-body">حين يقدّم المطعم تحليلاً مخبرياً لطبقه نفسه، نعرض قيمه بمستوى "موثّق"، وهو أعلى مستوى ثقة عندنا.</p>
            </aside>
        </div>
    </section>

    <section class="ns-section ns-section--sand" aria-labelledby="fit-title">
        <div class="ns-wrap ns-method__split">
            <div>
                <p class="ns-eyebrow">مدى مناسبة الوجبة لك</p>
                <h2 class="t-title" id="fit-title">كيف نقرر أن وجبة تناسبك</h2>
                <p class="t-body">نحسب احتياجك اليومي من عمرك وجسمك ونشاطك وهدفك، وحسب قالبك الغذائي وحالتك الصحية إن وُجدت، أو كما حددها أخصائيك. ثم نقارن الوجبة بحصة وجبة واحدة: <span class="ns-num">{{ $mealShare }}%</span> من يومك.</p>
                <p class="t-body ns-muted">نذكر دائماً الأسباب، ونقترح حصة أصغر إن كانت تناسبك. وما ينقص الوجبة (كالألياف) ملاحظة لتكمّلها في وجبة أخرى، لا سبب لخفض الدرجة.</p>
                <a class="ns-btn ns-btn--primary" href="{{ route('site.meals') }}">تصفّح الوجبات</a>
            </div>
            <ul class="ns-method__levels">
                @foreach ($fitLevels as $level => $rule)
                    <li>
                        @include('site.components.fit-verdict', ['level' => $level, 'compact' => true])
                        <p>{{ $rule }}</p>
                    </li>
                @endforeach
            </ul>
        </div>
    </section>
@endsection
