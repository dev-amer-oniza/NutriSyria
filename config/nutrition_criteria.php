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
| References (the owner's plan for the medical part, step 1): a rule that is checked against
| its sources names them with 'refs' (keys of config/nutrition_references.php), and 'kind'
| says what kind of rule it is: strict (going over makes a meal unfit), tolerance, guidance
| (a target or general advice) or shortfall (a minimum: a note only). 'info_only' rules are
| shown but never judge a meal. A rule without 'refs' is not verified yet.
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
    | How far a meal may go over its share of a limit before the verdict drops
    | (App\Services\Nutrition\Fit\MealFitCalculator, the owner's decision 1a):
    | up to 10% over = suits very well (4), up to 25% = in moderation (3),
    | up to 50% = needs attention (2), more than 50% = not for you now (1).
    */
    'fit_tolerance' => ['suits' => 10, 'moderate' => 25, 'unfit' => 50],

    /*
    | Targets whose overshoot counts like a limit when judging a meal. Every "max" counts;
    | being under a target or a "min" (fibre, protein, iron) is only a note (decision 2a).
    */
    'fit_target_limits' => ['energy_kcal', 'carbohydrate_g', 'fat_g'],

    /*
    | Over a limit by less than this (%), the reason says "بحدود" instead of a percent, so
    | 15.1 g against 15 g never reads "15 g, and 15 g: 1% more" (the owner's decision 3a).
    */
    'fit_near_pct' => 5,

    /*
    | Legume fibre (the owner's decision 2a): for a person with a sensitive digestion
    | (HealthCondition::feelsLegumeFibre), a meal with a legume ingredient and at least this
    | much fibre in a serving gets a gentle note, never a lower level. Foods by their Arabic name.
    */
    'legume_fibre' => [
        'min_fiber_g' => 5,
        'foods' => ['حمص', 'فول', 'عدس', 'عدس أحمر', 'فاصولياء بيضاء', 'لوبيا', 'ترمس', 'بازلاء'],
    ],

    /*
    | Physical activity level => multiplier of the resting energy (App\Enums\ActivityLevel).
    | Each one inside the FAO/WHO/UNU ranges (fao_energy_2004, Table 5.3): sedentary or light
    | 1.40–1.69, active 1.70–1.99, vigorous 2.00–2.40 (the owner's decision 1a). The old
    | 1.2–1.9 had no source, and 1.2 and 1.375 were under the lowest level adults live at.
    */
    'activity_factors' => [
        'sedentary' => 1.40,
        'light' => 1.55,
        'moderate' => 1.75,
        'very' => 2.00,
        'extreme' => 2.20,
    ],
    'activity_refs' => ['fao_energy_2004'],

    /*
    | Calories added to the daily need for each goal (App\Enums\Goal).
    |  - lose: a 500 kcal deficit, for a body mass index from 'deficit_min_bmi' only: the
    |    reference is for overweight and obesity (aha_obesity_2013; decision 2a). Below it the
    |    day stays at maintenance, and the person is told why.
    |  - gain: +300 has no guideline, so meals are judged on maintenance and the extra is
    |    only shown (decision 3a): 'surplus_judged' => false.
    */
    'goal_adjustment' => [
        'lose' => -500,
        'maintain' => 0,
        'gain' => 300,
    ],
    'deficit_min_bmi' => 25,
    'surplus_judged' => false,
    'goal_refs' => ['aha_obesity_2013'],

    /*
    | The nutritionist's nutrition plan (App\Services\Nutrition\Plan, the owner's decisions
    | 1a 2a 3a 4a). It overrides the automatic calories and macros for one client:
    |  - equations: the resting energy formulas offered (App\Enums\EnergyEquation);
    |  - adjustments: the ready-made calorie changes; any value in adjustment_range can be set
    |    with the slider. -750 and +500 are for nutritionists only (3a): people keep goal_adjustment;
    |  - macro_range: what each share of calories may be set to, in %; the three make 100;
    |  - fluids: ml per kg suggested, rounded to fluids_step; none for the conditions where
    |    fluids are the doctor's call (2a), so the nutritionist types them.
    */
    'plan' => [
        'adjustments' => [
            -750 => 'عجز شديد',
            -500 => 'عجز قياسي',
            0 => 'ثبات',
            300 => 'فائض معتدل',
            500 => 'فائض قياسي',
        ],
        'adjustment_range' => [-1000, 1000],
        'adjustment_step' => 50,
        'macro_range' => [5, 80],
        'fluids_ml_per_kg' => 35,
        'fluids_step' => 50,
        'fluids_range' => [500, 6000],
        'fluids_manual_for' => ['heart_failure', 'kidney', 'dialysis'],
        'body_fat_range' => [3, 70],

        /*
        | Meals (phase 2, decisions 1a 2a 3a 4a): their shares of the calories must make 100;
        | grams left unshared are only a warning. 'lunch' is the meal restaurant dishes will be
        | judged against (phase 3).
        */
        'meal_types' => [
            'breakfast' => 'فطور',
            'snack' => 'سناك',
            'lunch' => 'غداء',
            'dinner' => 'عشاء',
            'other' => 'وجبة أخرى',
        ],
        'meal_presets' => [
            'standard' => [
                'label' => 'التوزيع القياسي (5 وجبات)',
                'meals' => [['breakfast', '08:00', 25], ['snack', '11:00', 10], ['lunch', '14:00', 35], ['snack', '17:00', 10], ['dinner', '20:00', 20]],
            ],
            'three' => [
                'label' => '3 وجبات',
                'meals' => [['breakfast', '08:00', 30], ['lunch', '14:00', 40], ['dinner', '20:00', 30]],
            ],
        ],
        'max_meals' => 8,
    ],

    /*
    | Never suggest fewer calories than this (App\Enums\Sex): the lowest end of the
    | reference's weight-loss diets, 1200–1500 for women and 1500–1800 for men (aha_obesity_2013).
    */
    'min_kcal_refs' => ['aha_obesity_2013'],
    'min_kcal' => [
        'female' => 1200,
        'male' => 1500,
    ],

    /*
    | Body mass index = weight (kg) / height (m)². The owner's classes (WHO); each one applies
    | below its 'below' value (the last one has none). People see the gentle 'label', the
    | nutritionist the clinical one (decision 3a). The value is rounded to one decimal first.
    | 'warn' names the goals the class should not be chased with (decision 2a: a warning, never
    | a block); 'not_for' lists the conditions the index says nothing about (pregnancy).
    */
    'bmi' => [
        'classes' => [
            ['key' => 'underweight', 'below' => 18.5, 'label' => 'أقل من الطبيعي', 'clinical' => 'نقص الوزن', 'warn' => ['lose']],
            ['key' => 'normal', 'below' => 25, 'label' => 'ضمن الطبيعي', 'clinical' => 'وزن طبيعي'],
            ['key' => 'overweight', 'below' => 30, 'label' => 'أعلى من الطبيعي', 'clinical' => 'زيادة وزن'],
            ['key' => 'obese_1', 'below' => 35, 'label' => 'أعلى بكثير من الطبيعي (الدرجة الأولى)', 'clinical' => 'سمنة درجة أولى (خفيفة)', 'warn' => ['gain']],
            ['key' => 'obese_2', 'below' => 40, 'label' => 'أعلى بكثير من الطبيعي (الدرجة الثانية)', 'clinical' => 'سمنة درجة ثانية (متوسطة)', 'warn' => ['gain']],
            ['key' => 'obese_3', 'below' => null, 'label' => 'أعلى بكثير من الطبيعي (الدرجة الثالثة)', 'clinical' => 'سمنة درجة ثالثة (مفرطة)', 'warn' => ['gain']],
        ],
        /* The healthy range, turned into kilograms for the person's height. */
        'healthy' => [18.5, 24.9],
        /* 18.5, 25 and 30 read from WHO; the obesity classes 35 and 40 are not read yet. */
        'refs' => ['who_bmi'],
        'not_for' => ['pregnant_or_breastfeeding', 'gestational_diabetes'],
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
            'nutrient' => 'energy_kcal', 'kind' => 'guidance', 'refs' => ['mifflin_1990', 'fao_energy_2004', 'aha_obesity_2013'],
            'default' => ['type' => 'energy'],
            'range' => [800, 6000],
        ],
        'carbohydrate_g' => [
            'label' => 'الكربوهيدرات', 'unit' => 'غ', 'bound' => 'target', 'scope' => 'day',
            'nutrient' => 'carbohydrate_g', 'kind' => 'guidance', 'refs' => ['nasem_amdr'],
            'default' => ['type' => 'macro', 'macro' => 'carbs'],
            'range' => [20, 900],
        ],
        'protein_g' => [
            'label' => 'البروتين', 'unit' => 'غ', 'bound' => 'target', 'scope' => 'day',
            'nutrient' => 'protein_g', 'kind' => 'guidance', 'refs' => ['nasem_amdr'],
            'default' => ['type' => 'macro', 'macro' => 'protein'],
            'range' => [20, 400],
        ],
        /*
        | The least protein a day (the RDA), what a meal's protein note is measured against
        | (the owner's decision 4a): the share of a template's calories is a target, not a need.
        */
        'protein_min_g' => [
            'label' => 'البروتين (الحد الأدنى)', 'unit' => 'غ', 'bound' => 'min', 'scope' => 'day',
            'nutrient' => 'protein_g', 'kind' => 'shortfall', 'refs' => ['dga_2020_appendix'],
            'default' => ['type' => 'by_sex', 'female' => 46, 'male' => 56],
            'range' => [20, 300],
        ],
        'fat_g' => [
            'label' => 'الدهون', 'unit' => 'غ', 'bound' => 'target', 'scope' => 'day',
            'nutrient' => 'fat_g', 'kind' => 'guidance', 'refs' => ['nasem_amdr', 'who_fats_2023'],
            'default' => ['type' => 'macro', 'macro' => 'fat'],
            'range' => [10, 300],
        ],
        'fat_sat_g' => [
            'label' => 'الدهون المشبعة', 'unit' => 'غ', 'bound' => 'max', 'scope' => 'day',
            'nutrient' => 'fat_sat_g', 'kind' => 'guidance', 'refs' => ['who_fats_2023', 'dga_2025_saturated'],
            'default' => ['type' => 'energy_pct', 'pct' => 10, 'kcal_per_g' => 9],
            'range' => [5, 100],
        ],
        'sugars_g' => [
            'label' => 'السكريات الكلية', 'unit' => 'غ', 'bound' => 'max', 'scope' => 'day',
            'nutrient' => 'sugars_g', 'kind' => 'guidance', 'refs' => ['who_free_sugars_2015'],
            // The reference is for free sugars; ours include fruit and milk sugar (decision 11a).
            'info_only' => true,
            'default' => ['type' => 'energy_pct', 'pct' => 10, 'kcal_per_g' => 4],
            'range' => [5, 300],
            'note' => 'للمعلومة فقط، لا تدخل في تقييم الوجبات: توصية WHO عن السكر المضاف والعسل والعصير، وأرقامنا تشمل سكر الفواكه والحليب لأن مصدرنا لا يفصله.',
        ],
        'fiber_g' => [
            'label' => 'الألياف', 'unit' => 'غ', 'bound' => 'min', 'scope' => 'day',
            'nutrient' => 'fiber_g', 'kind' => 'shortfall', 'refs' => ['dga_2020_appendix', 'who_fibre_2023'],
            'default' => ['type' => 'per_1000', 'value' => 14],
            'range' => [5, 100],
        ],
        'sodium_mg' => [
            'label' => 'الصوديوم', 'unit' => 'ملغ', 'bound' => 'max', 'scope' => 'day',
            'nutrient' => 'sodium_mg', 'kind' => 'guidance', 'refs' => ['who_sodium', 'dga_2025_sodium'],
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
            'label' => 'المتوازن', 'group' => 'general', 'selectable' => true, 'source' => 'NASEM · WHO',
            'refs' => ['nasem_amdr', 'who_fats_2023'],
            'description' => 'توزيع للبالغين الأصحاء داخل المجالات المرجعية: كربوهيدرات 50%، بروتين 20%، دهون 30%.',
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
        /*
        | Digestion (step 7, batch 1, decisions 1a 3a): the references give no grams of fat for
        | these, and for the gallbladder and the pancreas they advise against restricting it
        | (nice_gallstones_cg188, ueg_pei_2025). Their meal fat limits went; the templates stay
        | in the nutritionist's library, judged with the balanced shares unless they choose one.
        */
        'gerd' => [
            'label' => 'الارتجاع المريئي', 'group' => 'digestion', 'source' => 'NICE · NIDDK',
            'description' => 'تجنّب ما يزيد الحرقة عندك، ووجبة رئيسية قبل النوم بثلاث ساعات على الأقل.',
            'macros' => ['carbs' => 50, 'protein' => 20, 'fat' => 30],
        ],
        'post_cholecystectomy' => [
            'label' => 'ما بعد استئصال المرارة', 'group' => 'digestion', 'source' => 'NICE',
            'description' => 'بعد العملية لا يلزم تجنّب أطعمة بعينها؛ ما يتعبك منها يحدده طبيبك.',
            'macros' => ['carbs' => 55, 'protein' => 20, 'fat' => 25],
        ],
        'pancreatitis' => [
            'label' => 'التهاب البنكرياس', 'group' => 'digestion', 'source' => 'UEG/ESPEN · NICE',
            'description' => 'وجبات صغيرة متكررة، والدسم يحدده أخصائي التغذية: تقليله كثيراً قد يضر.',
            'macros' => ['carbs' => 65, 'protein' => 20, 'fat' => 15],
            'see_nutritionist' => 'التهاب البنكرياس يحتاج أخصائي تغذية: خطر سوء التغذية فيه عالٍ.',
        ],
        'celiac' => [
            'label' => 'السيلياك (حساسية الغلوتين)', 'group' => 'digestion', 'source' => 'ACG',
            'description' => 'منع الغلوتين تماماً (القمح والشعير والشوفان العادي).',
            'macros' => ['carbs' => 55, 'protein' => 15, 'fat' => 30],
            // Gluten is the verified, strict part (niddk_celiac); the macros are not checked yet.
            'allergens' => ['gluten'], 'allergen_refs' => ['niddk_celiac'],
            'avoid_reason' => 'ومرض السيلياك يُعالَج بتجنّبه تماماً',
        ],
        /*
        | No condition brings it any more (step 3): cirrhosis brings its verified rules itself.
        | Kept in the nutritionist's library. The sodium rule went: EASL's 2 g is for ascites only
        | and equals the general limit.
        */
        'hepatic' => [
            'label' => 'تليّف الكبد', 'group' => 'digestion', 'source' => 'EASL · ESPEN',
            'description' => 'بروتين كافٍ وطاقة كافية، ووجبات صغيرة متكررة مع وجبة خفيفة قبل النوم.',
            'macros' => ['carbs' => 55, 'protein' => 15, 'fat' => 30],
            'rules' => [
                'protein_g' => ['type' => 'per_kg', 'value' => 1.2, 'bound' => 'min', 'refs' => ['easl_nutrition_2019']],
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
        /*
        | The liver (step 3, decisions 1a 2a 3a 4a). What each reference says:
        |  - fatty liver: a Mediterranean pattern and weight loss, with no figure for saturated
        |    fat or sugars (easl_masld_2024): the general limits and the deficit apply; the old
        |    7% and 5% had no source and went.
        |  - acute (A, E) and chronic (B, C) hepatitis: healthy food, fluids, no alcohol, no
        |    figures: general needs, verified.
        |  - cirrhosis: protein at least 1.2 g/kg (a floor on protein_min_g), and at least 35 kcal
        |    per kg with no deficit under a body mass index of 30 (easl_nutrition_2019; ESPEN 51:
        |    no more energy for the overweight or obese).
        |  - jaundice: a sign, not a disease: no limits until the cause is known.
        |  - liver_cirrhosis: the old mixed choice; general needs until the person picks again.
        */
        'fatty_liver' => ['refs' => ['easl_masld_2024']],
        'hepatitis_acute' => ['refs' => ['who_hepatitis_a', 'who_hepatitis_e', 'niddk_hepatitis_a']],
        'hepatitis_chronic' => ['refs' => ['niddk_hepatitis_b', 'niddk_hepatitis_c']],
        'cirrhosis' => [
            'criteria' => ['protein_min_g' => ['type' => 'per_kg', 'value' => 1.2, 'refs' => ['easl_nutrition_2019']]],
            'energy_min_per_kg' => 35,
            'energy_min_below_bmi' => 30,
            'energy_refs' => ['easl_nutrition_2019', 'espen_liver_2019'],
            'see_nutritionist' => 'تليّف الكبد يحتاج متابعة أخصائي.',
        ],
        'liver_cirrhosis' => [],
        'jaundice' => [
            'see_nutritionist' => 'اليرقان له أكثر من سبب، ولكل سبب غذاؤه. راجع طبيبك ليعرف السبب، ثم اختر الحالة الأدق.',
        ],
        /*
        | Digestion (step 7, batch 1): what the references say has no figure, so these bring the
        | general needs, verified, and their advice in words (HealthCondition::hint). A doctor's
        | fat limit, typed in معاييري, judges as a strict one.
        |  - reflux: avoid what brings the symptoms, the main meal well before bed, weight loss if
        |    overweight (nice_gord_cg184, niddk_gerd).
        |  - gallbladder removed: nothing needs avoiding after the operation (nice_gallstones_cg188).
        |  - fat intolerance: avoid what brings the symptoms (nice_gallstones_cg188); no figure.
        |  - pancreatitis: no fat restriction with enzymes, a dietitian for everyone, small frequent
        |    meals (ueg_pei_2025, nice_pancreatitis_ng104, niddk_pancreatitis).
        */
        'reflux' => ['refs' => ['nice_gord_cg184', 'niddk_gerd']],
        'gallbladder_removed' => ['refs' => ['nice_gallstones_cg188']],
        'fat_intolerance' => [
            'refs' => ['nice_gallstones_cg188'],
            'see_nutritionist' => 'عدم تحمّل الدسم قد يكون له سبب يحتاج تشخيصاً (المرارة أو البنكرياس). راجع أخصائياً، وإن أعطاك طبيبك حداً للدهون، اكتبه في معاييري فنقيّم عليه.',
        ],
        'pancreatitis' => [
            'refs' => ['ueg_pei_2025', 'nice_pancreatitis_ng104', 'niddk_pancreatitis'],
            'see_nutritionist' => 'التهاب البنكرياس يحتاج أخصائي تغذية: خطر سوء التغذية فيه عالٍ، ولا تقلّل الدسم كثيراً من نفسك. إن أعطاك طبيبك حداً للدهون، اكتبه في معاييري.',
        ],
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
