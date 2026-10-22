<?php

/*
| The criteria every meal is judged against, and how each one is worked out by default
| from a person's profile (App\Services\Nutrition\Criteria). Approved by the project owner,
| following international guidance (ADA, AHA, KDOQI, ESPEN, WHO).
|
| 1. Daily calories: Mifflin-St Jeor x activity level, plus the goal's change.
| 2. A diet template shares those calories between carbohydrate, protein and fat (the
|    nutritionist's way of working). The template comes from the person's conditions (the
|    highest in 'template_priority' wins), else the default; a person may pick one of the
|    'selectable' templates, and their nutritionist any template.
| 3. Other criteria (sugars, fibre, sodium, saturated fat, iron...) come from their own rules.
|    The winning template's rules apply as they are; from the other templates and the
|    conditions only ceilings and floors apply, and the stricter one wins.
|
| A person can override any value, and their nutritionist's value overrides both.
|
| Rule types (CriteriaCalculator):
|   energy      the daily calorie target
|   macro       the template's share of calories for carbs, protein or fat, in grams
|   per_kg      grams per kg of body weight; 'by_goal' or a single 'value'
|   energy_pct  a share of the daily calories, turned into grams with 'kcal_per_g'
|   per_1000    grams per 1000 kcal of the daily target
|   fixed       a fixed amount
|   by_sex      a fixed amount per sex ('female' / 'male')
*/

return [

    /* Personal calculations start at this age; the formula is for adults. */
    'min_age' => 18,

    /* One restaurant meal = this share of the day (the main meal). */
    'meal_share' => 0.30,

    /*
    | How far a meal may stray from its share of a target before the verdict drops
    | (phase 3, the meal analysis): within 10% suits, within 25% suits in moderation.
    */
    'fit_tolerance' => ['suits' => 10, 'moderate' => 25],

    /* Physical activity level => multiplier of the resting energy (App\Enums\ActivityLevel). */
    'activity_factors' => [
        'sedentary' => 1.2,
        'light' => 1.375,
        'moderate' => 1.55,
        'very' => 1.725,
        'extreme' => 1.9,
    ],

    /* Calories added to the daily need for each goal (App\Enums\Goal). */
    'goal_adjustment' => [
        'lose' => -500,
        'maintain' => 0,
        'gain' => 300,
    ],

    /* Never suggest fewer calories than this (App\Enums\Sex). */
    'min_kcal' => [
        'female' => 1200,
        'male' => 1500,
    ],

    /* Calories per gram, to turn a share of the day into grams. */
    'kcal_per_g' => ['carbs' => 4, 'protein' => 4, 'fat' => 9],

    /*
    | Each criterion: label, unit, bound (min | max | target), scope (day | meal: a meal
    | criterion is not scaled by meal_share), the nutrient it is compared with, the default
    | rule (null: only a template, condition, person or nutritionist turns it on), and the
    | range a person may set it to.
    */
    'criteria' => [
        'energy_kcal' => [
            'label' => 'السعرات', 'unit' => 'سعرة', 'bound' => 'target', 'scope' => 'day',
            'nutrient' => 'energy_kcal',
            'default' => ['type' => 'energy'],
            'range' => [800, 6000],
        ],
        'carbohydrate_g' => [
            'label' => 'الكربوهيدرات', 'unit' => 'غ', 'bound' => 'target', 'scope' => 'day',
            'nutrient' => 'carbohydrate_g',
            'default' => ['type' => 'macro', 'macro' => 'carbs'],
            'range' => [20, 900],
        ],
        'protein_g' => [
            'label' => 'البروتين', 'unit' => 'غ', 'bound' => 'target', 'scope' => 'day',
            'nutrient' => 'protein_g',
            'default' => ['type' => 'macro', 'macro' => 'protein'],
            'range' => [20, 400],
        ],
        'fat_g' => [
            'label' => 'الدهون', 'unit' => 'غ', 'bound' => 'target', 'scope' => 'day',
            'nutrient' => 'fat_g',
            'default' => ['type' => 'macro', 'macro' => 'fat'],
            'range' => [10, 300],
        ],
        'fat_sat_g' => [
            'label' => 'الدهون المشبعة', 'unit' => 'غ', 'bound' => 'max', 'scope' => 'day',
            'nutrient' => 'fat_sat_g',
            'default' => ['type' => 'energy_pct', 'pct' => 10, 'kcal_per_g' => 9],
            'range' => [5, 100],
        ],
        'sugars_g' => [
            'label' => 'السكريات الكلية', 'unit' => 'غ', 'bound' => 'max', 'scope' => 'day',
            'nutrient' => 'sugars_g',
            'default' => ['type' => 'energy_pct', 'pct' => 10, 'kcal_per_g' => 4],
            'range' => [5, 300],
            'note' => 'تشمل سكر الفواكه والحليب الطبيعي، لأن مصدرنا لا يفصل السكر المضاف.',
        ],
        'fiber_g' => [
            'label' => 'الألياف', 'unit' => 'غ', 'bound' => 'min', 'scope' => 'day',
            'nutrient' => 'fiber_g',
            'default' => ['type' => 'per_1000', 'value' => 14],
            'range' => [5, 100],
        ],
        'sodium_mg' => [
            'label' => 'الصوديوم', 'unit' => 'ملغ', 'bound' => 'max', 'scope' => 'day',
            'nutrient' => 'sodium_mg',
            'default' => ['type' => 'fixed', 'value' => 2000],
            'range' => [500, 6000],
        ],
        'meal_carbohydrate_g' => [
            'label' => 'كربوهيدرات الوجبة', 'unit' => 'غ', 'bound' => 'max', 'scope' => 'meal',
            'nutrient' => 'carbohydrate_g',
            'default' => null,
            'range' => [10, 200],
        ],
        'meal_fat_g' => [
            'label' => 'دهون الوجبة', 'unit' => 'غ', 'bound' => 'max', 'scope' => 'meal',
            'nutrient' => 'fat_g',
            'default' => null,
            'range' => [5, 100],
        ],
        'iron_mg' => [
            'label' => 'الحديد', 'unit' => 'ملغ', 'bound' => 'min', 'scope' => 'day',
            'nutrient' => 'iron_mg',
            'default' => null,
            'range' => [5, 60],
        ],
        'calcium_mg' => [
            'label' => 'الكالسيوم', 'unit' => 'ملغ', 'bound' => 'min', 'scope' => 'day',
            'nutrient' => 'calcium_mg',
            'default' => null,
            'range' => [500, 2500],
        ],
    ],

    /* The template everyone without a condition starts from. */
    'default_template' => 'balanced',

    /* When conditions bring several templates, the first in this list is used. */
    'template_priority' => [
        'dialysis', 'ckd', 'pancreatitis', 'hepatic', 'gdm', 'pregnancy', 'diabetes', 'chf',
        'celiac', 'metabolic_syndrome', 'post_cholecystectomy', 'gerd', 'dash', 'dyslipidemia',
        'gout', 'osteoporosis', 'menopause', 'mediterranean', 'balanced',
    ],

    /*
    | Diet templates: macros in % of calories (carbs / protein / fat), extra rules, allergens
    | to avoid, the group shown in the library, and whether a person may pick it themselves.
    | Children's templates are left out: personal calculations start at 18.
    */
    'templates' => [
        'balanced' => [
            'label' => 'المتوازن (ADA)', 'group' => 'general', 'selectable' => true, 'source' => 'ADA',
            'description' => 'التوزيع المتوازن المعتمد للبالغين الأصحاء.',
            'macros' => ['carbs' => 50, 'protein' => 20, 'fat' => 30],
        ],
        'mediterranean' => [
            'label' => 'حمية البحر المتوسط', 'group' => 'general', 'selectable' => true, 'source' => 'AHA',
            'description' => 'غني بالخضار والحبوب الكاملة وزيت الزيتون والسمك، لصحة القلب والكبد.',
            'macros' => ['carbs' => 50, 'protein' => 15, 'fat' => 35],
        ],
        'dash' => [
            'label' => 'DASH لضغط الدم', 'group' => 'heart', 'selectable' => true, 'source' => 'NHLBI',
            'description' => 'غني بالحبوب الكاملة والفواكه والخضار والألبان قليلة الدسم، وقليل الملح.',
            'macros' => ['carbs' => 55, 'protein' => 18, 'fat' => 27],
            'rules' => ['sodium_mg' => ['type' => 'fixed', 'value' => 1500]],
        ],
        'chf' => [
            'label' => 'قصور القلب', 'group' => 'heart', 'source' => 'AHA',
            'description' => 'تحكّم بالصوديوم والسوائل لدعم كفاءة القلب.',
            'macros' => ['carbs' => 50, 'protein' => 20, 'fat' => 30],
            'rules' => ['sodium_mg' => ['type' => 'fixed', 'value' => 1500]],
            'see_nutritionist' => 'قصور القلب يحتاج ضبطاً للسوائل والصوديوم يحدده لك أخصائي.',
        ],
        'dyslipidemia' => [
            'label' => 'ارتفاع الدهون والكولسترول', 'group' => 'metabolic', 'source' => 'AHA',
            'description' => 'ألياف ذائبة أكثر، ودهون مشبعة أقل.',
            'macros' => ['carbs' => 50, 'protein' => 20, 'fat' => 30],
            'rules' => ['fat_sat_g' => ['type' => 'energy_pct', 'pct' => 6, 'kcal_per_g' => 9]],
        ],
        'diabetes' => [
            'label' => 'السكري (تحكّم بالكربوهيدرات)', 'group' => 'metabolic', 'source' => 'ADA',
            'description' => 'توزيع الكربوهيدرات على الوجبات، حتى 60 غ في الوجبة.',
            'macros' => ['carbs' => 45, 'protein' => 20, 'fat' => 35],
            'rules' => [
                'meal_carbohydrate_g' => ['type' => 'fixed', 'value' => 60],
                'sugars_g' => ['type' => 'energy_pct', 'pct' => 5, 'kcal_per_g' => 4],
            ],
        ],
        'gdm' => [
            'label' => 'سكري الحمل', 'group' => 'metabolic', 'source' => 'ADA / ACOG',
            'description' => 'مراقبة الكربوهيدرات للحامل، حتى 45 غ في الوجبة.',
            'macros' => ['carbs' => 40, 'protein' => 25, 'fat' => 35],
            'rules' => [
                'meal_carbohydrate_g' => ['type' => 'fixed', 'value' => 45],
                'sugars_g' => ['type' => 'energy_pct', 'pct' => 5, 'kcal_per_g' => 4],
                'iron_mg' => ['type' => 'fixed', 'value' => 27],
            ],
            'no_deficit' => true,
            'see_nutritionist' => 'سكري الحمل يحتاج متابعة أخصائية وطبيبك.',
        ],
        'metabolic_syndrome' => [
            'label' => 'متلازمة التمثيل الغذائي', 'group' => 'metabolic', 'source' => 'AHA',
            'description' => 'كربوهيدرات منخفضة المؤشر الجلايسيمي، ودهون أحادية صحية.',
            'macros' => ['carbs' => 40, 'protein' => 25, 'fat' => 35],
            'rules' => ['sugars_g' => ['type' => 'energy_pct', 'pct' => 5, 'kcal_per_g' => 4]],
        ],
        'ckd' => [
            'label' => 'الكلى قبل الغسيل (CKD 3-5)', 'group' => 'kidney', 'source' => 'KDOQI 2020',
            'description' => 'بروتين منخفض (حتى 0.6 غ لكل كغ) لحماية الكلى.',
            'macros' => ['carbs' => 55, 'protein' => 10, 'fat' => 35],
            'rules' => [
                'protein_g' => ['type' => 'per_kg', 'value' => 0.6, 'bound' => 'max'],
                'sodium_mg' => ['type' => 'fixed', 'value' => 2000],
            ],
            'see_nutritionist' => 'أمراض الكلى تحتاج أرقاماً شخصية (البوتاسيوم والفوسفور) يحددها لك أخصائي.',
        ],
        'dialysis' => [
            'label' => 'غسيل الكلى', 'group' => 'kidney', 'source' => 'KDOQI 2020',
            'description' => 'بروتين أعلى (1.2 غ لكل كغ على الأقل) لتعويض ما يفقده الغسيل.',
            'macros' => ['carbs' => 50, 'protein' => 20, 'fat' => 30],
            'rules' => [
                'protein_g' => ['type' => 'per_kg', 'value' => 1.2, 'bound' => 'min'],
                'sodium_mg' => ['type' => 'fixed', 'value' => 2000],
            ],
            'see_nutritionist' => 'الغسيل يحتاج أرقاماً شخصية (البوتاسيوم والفوسفور والسوائل) يحددها لك أخصائي.',
        ],
        'gerd' => [
            'label' => 'الارتجاع المريئي', 'group' => 'digestion', 'source' => 'ACG',
            'description' => 'حمية لطيفة، ووجبات أقل دسماً.',
            'macros' => ['carbs' => 50, 'protein' => 20, 'fat' => 30],
            'rules' => ['meal_fat_g' => ['type' => 'fixed', 'value' => 20]],
        ],
        'post_cholecystectomy' => [
            'label' => 'ما بعد استئصال المرارة', 'group' => 'digestion', 'source' => 'ESPEN',
            'description' => 'دهون أقل موزّعة على وجبات صغيرة.',
            'macros' => ['carbs' => 55, 'protein' => 20, 'fat' => 25],
            'rules' => ['meal_fat_g' => ['type' => 'fixed', 'value' => 20]],
        ],
        'pancreatitis' => [
            'label' => 'التهاب البنكرياس', 'group' => 'digestion', 'source' => 'ESPEN',
            'description' => 'دهون قليلة (15% من السعرات، ضمن 30 إلى 50 غ في اليوم) لتسهيل الهضم.',
            'macros' => ['carbs' => 65, 'protein' => 20, 'fat' => 15],
            'rules' => ['meal_fat_g' => ['type' => 'fixed', 'value' => 15]],
            'see_nutritionist' => 'التهاب البنكرياس يحتاج متابعة أخصائي.',
        ],
        'celiac' => [
            'label' => 'السيلياك (حساسية الغلوتين)', 'group' => 'digestion', 'source' => 'ACG',
            'description' => 'منع الغلوتين تماماً (القمح والشعير والشوفان العادي).',
            'macros' => ['carbs' => 55, 'protein' => 15, 'fat' => 30],
            'allergens' => ['gluten'],
        ],
        'hepatic' => [
            'label' => 'الكبد الوبائي والتليّف', 'group' => 'digestion', 'source' => 'ESPEN',
            'description' => 'بروتين جيد كافٍ وطاقة كافية لدعم الكبد.',
            'macros' => ['carbs' => 55, 'protein' => 15, 'fat' => 30],
            'rules' => [
                'protein_g' => ['type' => 'per_kg', 'value' => 1.2, 'bound' => 'min'],
                'sodium_mg' => ['type' => 'fixed', 'value' => 2000],
            ],
            'see_nutritionist' => 'أمراض الكبد تحتاج متابعة أخصائي.',
        ],
        'gout' => [
            'label' => 'النقرس', 'group' => 'other', 'source' => 'ACR',
            'description' => 'قليل البيورين، يعتمد على البروتين النباتي والألبان قليلة الدسم.',
            'macros' => ['carbs' => 55, 'protein' => 15, 'fat' => 30],
            'rules' => ['sugars_g' => ['type' => 'energy_pct', 'pct' => 5, 'kcal_per_g' => 4]],
            'see_nutritionist' => 'النقرس يتأثر بالبيورينات (اللحوم الحمراء والأحشاء وبعض الأسماك)، وهي غير محسوبة عندنا بعد.',
        ],
        'osteoporosis' => [
            'label' => 'هشاشة العظام', 'group' => 'other', 'source' => 'NOF',
            'description' => 'كالسيوم كافٍ (1200 ملغ) وفيتامين D.',
            'macros' => ['carbs' => 50, 'protein' => 20, 'fat' => 30],
            'rules' => ['calcium_mg' => ['type' => 'fixed', 'value' => 1200]],
        ],
        'pregnancy' => [
            'label' => 'الحمل والرضاعة', 'group' => 'other', 'source' => 'ACOG',
            'description' => 'احتياجات أعلى من البروتين والحديد والكالسيوم وحمض الفوليك.',
            'macros' => ['carbs' => 50, 'protein' => 20, 'fat' => 30],
            'rules' => ['iron_mg' => ['type' => 'fixed', 'value' => 27]],
            'no_deficit' => true,
            'see_nutritionist' => 'الحمل والرضاعة يغيّران احتياجك، فلا نقترح إنقاص الوزن. راجعي أخصائية تغذية.',
        ],
        'menopause' => [
            'label' => 'سن الأمل', 'group' => 'other', 'source' => 'NAMS',
            'description' => 'دعم العظام والتوازن الهرموني، بروتين أعلى قليلاً.',
            'macros' => ['carbs' => 45, 'protein' => 25, 'fat' => 30],
            'rules' => ['calcium_mg' => ['type' => 'fixed', 'value' => 1200]],
        ],
    ],

    /*
    | Health conditions a person declares (App\Enums\HealthCondition): the template each one
    | brings, and limits of its own. When several set the same limit, the stricter wins.
    */
    'conditions' => [
        'hypertension' => ['template' => 'dash'],
        'heart_disease' => [
            'template' => 'dash',
            'criteria' => ['fat_sat_g' => ['type' => 'energy_pct', 'pct' => 6, 'kcal_per_g' => 9]],
        ],
        'heart_failure' => ['template' => 'chf'],
        'diabetes' => ['template' => 'diabetes'],
        'gestational_diabetes' => ['template' => 'gdm'],
        'insulin_resistance' => ['template' => 'metabolic_syndrome'],
        'high_cholesterol' => ['template' => 'dyslipidemia'],
        'kidney' => ['template' => 'ckd'],
        'dialysis' => ['template' => 'dialysis'],
        'fatty_liver' => [
            'template' => 'mediterranean',
            'criteria' => [
                'sugars_g' => ['type' => 'energy_pct', 'pct' => 5, 'kcal_per_g' => 4],
                'fat_sat_g' => ['type' => 'energy_pct', 'pct' => 7, 'kcal_per_g' => 9],
            ],
        ],
        'liver_cirrhosis' => ['template' => 'hepatic'],
        'reflux' => ['template' => 'gerd'],
        'gallbladder_removed' => ['template' => 'post_cholecystectomy'],
        'fat_intolerance' => [
            'template' => 'post_cholecystectomy',
            'criteria' => ['meal_fat_g' => ['type' => 'fixed', 'value' => 15]],
            'see_nutritionist' => 'عدم تحمّل الدسم قد يكون له سبب يحتاج تشخيصاً (المرارة أو البنكرياس). راجع أخصائياً.',
        ],
        'pancreatitis' => ['template' => 'pancreatitis'],
        'celiac' => ['template' => 'celiac'],
        'gout' => ['template' => 'gout'],
        'anemia' => [
            'criteria' => ['iron_mg' => ['type' => 'by_sex', 'female' => 18, 'male' => 8]],
            'see_nutritionist' => 'فقر الدم له أسباب مختلفة، وعلاجه لا يكون بالغذاء وحده. راجع أخصائياً أو طبيباً.',
        ],
        'osteoporosis' => ['template' => 'osteoporosis'],
        'pregnant_or_breastfeeding' => ['template' => 'pregnancy'],
        'menopause' => ['template' => 'menopause'],
    ],

];
