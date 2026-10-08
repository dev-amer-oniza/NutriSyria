<?php

/*
| سجل "المحوّلات" (Adapters): كل مصدر ضخم يحتاج سكربت خاص به يُسجَّل هنا كسطر واحد.
| إضافة مصدر جديد لاحقاً = إضافة سطر هنا فقط، بلا تعديل أي كود آخر.
|
| script         : المسار داخل tools/ (نسبياً)
| releases_path  : المجلد الذي تُفكّ بداخله نسخ البيانات الخام (كل مجلد فرعي = نسخة/إصدار)
| release_prefix : (اختياري) بادئة يُتوقع أن يبدأ بها اسم مجلد النسخة، لربطه لاحقاً برمز
|                  مصدر البيانات في data_sources (سنستعملها في التحسين القادم)
*/
return [
    'usda_fdc' => [
        'label' => 'USDA FoodData Central',
        'script' => 'usda_to_template.py',
        'releases_path' => 'tools/fdc',
        'release_prefix' => 'usda_fdc_',
    ],

    // مثال لإضافة مصدر ضخم جديد لاحقاً (معطَّل الآن، فقط للتوضيح):
    // 'egyptian_table' => [
    //     'label' => 'الجدول المصري للتركيب الغذائي',
    //     'script' => 'egyptian_table_to_template.py',
    //     'releases_path' => 'tools/egyptian_table',
    //     'release_prefix' => 'egyptian_table_',
    // ],
];
