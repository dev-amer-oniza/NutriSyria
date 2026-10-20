@php($features = [
    ['icon' => 'chart', 'title' => 'قيم كل وجبة', 'text' => 'الطاقة والبروتين والكربوهيدرات والدهون والألياف لكل حصة من وجبات المطاعم.', 'route' => 'site.meals'],
    ['icon' => 'target', 'title' => 'مدى مناسبتها لك', 'text' => 'حكم واضح بأربع درجات وفق جسمك وهدفك وحالتك الصحية، ومعه الأسباب.', 'route' => 'register'],
    ['icon' => 'document', 'title' => 'مصدر كل رقم', 'text' => 'كل قيمة تظهر مع مستوى التحقق منها، واعتماد أخصائي التغذية.', 'route' => 'site.method'],
    ['icon' => 'qr', 'title' => 'رمز على الطاولة', 'text' => 'امسح رمز الوجبة في المطعم، فتظهر قيمها أمامك قبل أن تطلب.', 'route' => 'site.partners'],
])

<section class="ns-features" aria-label="ماذا تقدّم المنصة">
    <div class="ns-wrap ns-features__grid">
        @foreach ($features as $feature)
            @include('site.components.feature-tile', $feature + ['href' => \App\Support\Site\SiteLink::url($feature['route'])])
        @endforeach
    </div>
</section>
