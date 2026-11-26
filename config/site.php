<?php

/*
 * Public site settings shared by the Blade views in resources/views/site.
 * Links name a route in routes/web.php.
 */
return [

    'description' => 'القيم الغذائية لوجبات المطاعم السورية، موثّقة المصدر، مع مدى مناسبة كل وجبة لك.',

    /*
    | "يومي": the person's day. Hours and dates are in this zone (the server keeps UTC), and
    | the amounts one may log of a meal: plates, or 100 g steps for a meal with no plate weight.
    */
    'daily' => [
        'timezone' => 'Asia/Damascus',
        'plates' => [0.25, 0.5, 0.75, 1.0, 1.5, 2.0],
        'per_100g' => [1.0, 1.5, 2.0, 3.0, 4.0],
        'suggestions' => 4,
        // The weight: asked for again after this many days (a bell reminder, once), and how many
        // weighings the little line on the day shows.
        'weigh_every_days' => 7,
        'weight_points' => 8,
        'weight_kg' => ['min' => 30, 'max' => 300],
    ],

    'nav' => [
        ['label' => 'الرئيسية', 'route' => 'home'],
        ['label' => 'الوجبات', 'route' => 'site.meals'],
        ['label' => 'المطبخ السوري', 'route' => 'site.kitchen'],
        ['label' => 'المطاعم والبراندات', 'route' => 'site.brands'],
        ['label' => 'أخصائيو التغذية', 'route' => 'site.nutritionists'],
        ['label' => 'كيف نتحقق', 'route' => 'site.method'],
        ['label' => 'للمطاعم', 'route' => 'site.partners'],
    ],

    'footer' => [
        'المنصة' => [
            ['label' => 'الوجبات', 'route' => 'site.meals'],
            ['label' => 'المطبخ السوري', 'route' => 'site.kitchen'],
            ['label' => 'المطاعم والبراندات', 'route' => 'site.brands'],
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
            // The platform's own kitchen ("المطبخ السوري") is approved with no nutritionist review.
            'platform_text' => 'محسوبة من مكوّنات موثّقة المصدر وفق كمياتها في الوصفة.',
        ],
        'estimated' => [
            'label' => 'تقديري',
            'icon' => 'M2 10A8 8 0 0 1 10 2A8 8 0 0 1 2 10Z',
            'text' => 'أحد مكوّناتها لا تتوفر بياناته، فأخذنا أقرب غذاء له، ونذكر ذلك بوضوح مع القيم.',
        ],
    ],

    /*
     * The health goals (pages/goals, the home chips): key = the section's anchor on /goals.
     * What each one changes is told by App\Services\Site\HealthGoalGuide.
     */
    'goals' => [
        'weight-loss' => ['icon' => 'scale', 'label' => 'خفض الوزن'],
        'muscle' => ['icon' => 'chart', 'label' => 'بناء العضلات'],
        'fiber' => ['icon' => 'leaf', 'label' => 'ألياف أكثر'],
        'balanced' => ['icon' => 'bowl', 'label' => 'وجبات متوازنة'],
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

    /*
     * A meal's values under its four main figures (pages/meals/show), in groups. Each value:
     * code => [label, unit, always]. "always" values show "—" when unknown; the others show
     * only when the meal has them (the owner's decision 1a). A folded group opens on a click (2a).
     * Every code here is read by App\Services\Site\MealCatalog::NUTRIENTS.
     */
    'meal_values' => [
        ['title' => 'الدهون', 'folded' => false, 'values' => [
            'fat_sat_g' => ['المشبعة', 'غ', true],
            'fat_mono_g' => ['الأحادية غير المشبعة', 'غ', false],
            'fat_poly_g' => ['المتعددة غير المشبعة', 'غ', false],
            'cholesterol_mg' => ['الكوليسترول', 'ملغ', false],
        ]],
        ['title' => 'الكربوهيدرات', 'folded' => false, 'values' => [
            'sugars_g' => ['السكريات الكلية', 'غ', true],
        ]],
        ['title' => 'المعادن', 'folded' => false, 'values' => [
            'sodium_mg' => ['الصوديوم', 'ملغ', true],
            'potassium_mg' => ['البوتاسيوم', 'ملغ', false],
            'calcium_mg' => ['الكالسيوم', 'ملغ', true],
            'iron_mg' => ['الحديد', 'ملغ', true],
            'magnesium_mg' => ['المغنيسيوم', 'ملغ', false],
            'phosphorus_mg' => ['الفوسفور', 'ملغ', false],
            'zinc_mg' => ['الزنك', 'ملغ', false],
        ]],
        ['title' => 'الفيتامينات', 'folded' => true, 'values' => [
            'vitamin_a_rae_ug' => ['فيتامين A', 'مكغ', false],
            'vitamin_c_mg' => ['فيتامين C', 'ملغ', false],
            'vitamin_d_ug' => ['فيتامين D', 'مكغ', false],
            'vitamin_e_mg' => ['فيتامين E', 'ملغ', false],
            'vitamin_k_ug' => ['فيتامين K', 'مكغ', false],
            'thiamin_mg' => ['فيتامين B1', 'ملغ', false],
            'riboflavin_mg' => ['فيتامين B2', 'ملغ', false],
            'niacin_mg' => ['فيتامين B3', 'ملغ', false],
            'vitamin_b6_mg' => ['فيتامين B6', 'ملغ', false],
            'vitamin_b12_ug' => ['فيتامين B12', 'مكغ', false],
            'folate_ug' => ['الفولات', 'مكغ', false],
        ]],
    ],

];
