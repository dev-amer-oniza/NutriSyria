@php($steps = [
    ['title' => 'ابحث أو امسح', 'text' => 'اكتب اسم الوجبة أو المطعم، أو امسح الرمز الموجود على الطاولة.'],
    ['title' => 'افهم', 'text' => 'الطاقة والعناصر الغذائية لكل حصة، ومع كل رقم مصدره.'],
    ['title' => 'اعرف مناسبتها لك', 'text' => 'حكم بأربع درجات وفق بياناتك، مع الأسباب ونسبتها من احتياجك اليومي.'],
    ['title' => 'اختر حصتك', 'text' => 'نقترح عليك الحصة المناسبة لهدفك، فتطلب وأنت تعرف.'],
])

<section class="ns-section ns-section--sand" aria-labelledby="steps-title">
    <div class="ns-wrap">
        <div class="ns-section__head ns-section__head--center">
            <div>
                <p class="ns-eyebrow">كيف تعمل المنصة</p>
                <h2 class="t-title" id="steps-title">خطوة صغيرة، ثم ما يليها</h2>
                <p class="t-lead ns-muted">لا نطلب منك أن تغيّر كل شيء دفعة واحدة. نعطيك معلومة واضحة عن وجبتك، ونترك القرار لك.</p>
            </div>
        </div>

        <ol class="ns-steps">
            @foreach ($steps as $i => $step)
                <li class="ns-step">
                    <div class="ns-step__art">
                        @include('site.components.chain-tiles', ['pattern' => 'step-'.($i + 1), 'tile' => $i === 3 ? 48 : 96])
                    </div>
                    <span class="ns-step__n ns-num">{{ sprintf('%02d', $i + 1) }}</span>
                    <h3 class="ns-step__title">{{ $step['title'] }}</h3>
                    <p class="ns-step__text">{{ $step['text'] }}</p>
                </li>
            @endforeach
        </ol>
    </div>
</section>
