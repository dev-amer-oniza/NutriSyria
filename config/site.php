<?php

/*
 * Public site settings shared by the Blade views in resources/views/site.
 * Links name a route in routes/web.php.
 */
return [

    'description' => 'القيم الغذائية لوجبات المطاعم السورية، موثّقة المصدر، مع مدى مناسبة كل وجبة لك.',

    'nav' => [
        ['label' => 'الرئيسية', 'route' => 'home'],
        ['label' => 'الوجبات', 'route' => 'site.meals'],
        ['label' => 'المطاعم', 'route' => 'site.brands'],
        ['label' => 'أخصائيو التغذية', 'route' => 'site.nutritionists'],
        ['label' => 'كيف نتحقق', 'route' => 'site.method'],
        ['label' => 'للمطاعم', 'route' => 'site.partners'],
    ],

    'footer' => [
        'المنصة' => [
            ['label' => 'الوجبات', 'route' => 'site.meals'],
            ['label' => 'المطاعم', 'route' => 'site.brands'],
            ['label' => 'أخصائيو التغذية', 'route' => 'site.nutritionists'],
            ['label' => 'الأهداف الصحية', 'route' => 'site.goals'],
        ],
        'المعرفة' => [
            ['label' => 'منهجية التحقق', 'route' => 'site.method'],
            ['label' => 'المصادر', 'route' => 'site.sources'],
            ['label' => 'للمطاعم', 'route' => 'site.partners'],
        ],
        'NutriSyria' => [
            ['label' => 'من نحن', 'route' => 'site.about'],
            ['label' => 'تواصل معنا', 'route' => 'site.contact'],
            ['label' => 'سياسة الخصوصية', 'route' => 'site.privacy'],
        ],
    ],

    /*
     * Verification levels of a meal's figures (decided by the project owner):
     * verified = lab analysis of the dish itself; calculated = computed from
     * sourced ingredients and approved by a nutritionist; estimated = at least
     * one ingredient uses a close substitute food (a proxy).
     */
    'verification' => [
        'verified' => [
            'label' => 'موثّق',
            'icon' => 'M1 11A10 10 0 0 1 11 1A10 10 0 0 1 1 11Z',
            'text' => 'قيم الطبق نفسه من تحليل مخبري.',
        ],
        'calculated' => [
            'label' => 'محسوب',
            'icon' => 'M11 11H1A10 10 0 0 1 11 1Z',
            'text' => 'محسوبة من مكوّنات موثّقة المصدر وفق كمياتها في الوصفة، وراجعها أخصائي تغذية واعتمدها.',
        ],
        'estimated' => [
            'label' => 'تقديري',
            'icon' => 'M2 10A8 8 0 0 1 10 2A8 8 0 0 1 2 10Z',
            'text' => 'أحد مكوّناتها لا تتوفر بياناته، فأخذنا أقرب غذاء له، ونذكر ذلك بوضوح مع القيم.',
        ],
    ],

    /*
     * Data sources as the "how we verify" page names them, by data_sources.code. A source
     * missing here shows with its own name and no description.
     */
    'data_sources' => [
        'usda_fdc_foundation' => [
            'label' => 'USDA Foundation Foods',
            'text' => 'تحاليل مخبرية حديثة من وزارة الزراعة الأمريكية، تُحدَّث دورياً. نستعملها أولاً.',
        ],
        'usda_fdc_sr_legacy' => [
            'label' => 'USDA SR Legacy',
            'text' => 'المرجع الأمريكي الكلاسيكي للتركيب الغذائي، آخر إصدار له 2018. يغطي ما لا تغطيه Foundation.',
        ],
        'usda_fdc_fndds' => [
            'label' => 'USDA FNDDS',
            'text' => 'أطباق جاهزة كما تؤكل في المسوح الغذائية. لا نستعمله للمكوّنات.',
        ],
    ],

    /* How well a meal fits one person: four calm levels, always shown with the reasons. */
    'fit_levels' => [
        4 => 'مناسبة جداً',
        3 => 'مناسبة باعتدال',
        2 => 'تحتاج انتباهاً',
        1 => 'غير مناسبة لك الآن',
    ],

];
