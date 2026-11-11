{{-- "كيف نعمل؟": the four steps in one row. --}}
@php($steps = [
    ['icon' => 'search', 'title' => 'ابحث عن وجبة أو مطعم', 'text' => 'بالاسم، من كل الوجبات المعتمدة.'],
    ['icon' => 'chart', 'title' => 'اعرض القيم الغذائية', 'text' => 'لكل حصة، ومع كل رقم مصدره.'],
    ['icon' => 'target', 'title' => 'اعرف مناسبتها لك', 'text' => 'حكم بأربع درجات وفق بياناتك.'],
    ['icon' => 'bowl', 'title' => 'اختر حصتك بثقة', 'text' => 'نقترح الحصة الأنسب لهدفك.'],
])
<section class="ns-how" aria-labelledby="steps-title">
    <div class="ns-wrap ns-how__in">
        <div class="ns-how__head">
            <h2 class="ns-how__title" id="steps-title">كيف نعمل؟</h2>
            <p class="ns-how__lead">من الوجبة إلى القرار الغذائي الصحيح بخطوات بسيطة.</p>
        </div>
        <ol class="ns-how__steps">
            @foreach ($steps as $i => $step)
                <li class="ns-how__step">
                    <span class="ns-how__icon">@include('site.partials.icon', ['name' => $step['icon']])</span>
                    <div>
                        <span class="ns-how__n ns-num">{{ sprintf('%02d', $i + 1) }}</span>
                        <h3>{{ $step['title'] }}</h3>
                        <p>{{ $step['text'] }}</p>
                    </div>
                </li>
            @endforeach
        </ol>
    </div>
</section>
