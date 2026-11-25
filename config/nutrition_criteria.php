<?php

/*
| The criteria every meal is judged against, and how each one is worked out by default
| from a person's profile (App\Services\Nutrition\Criteria).
|
| The platform serves healthy adults and people who train, with their allergies; health
| conditions were removed (the owner's decision, Nov 2026; the old work is in main's history
| at b76d834). Daily calories: Mifflin-St Jeor × the day's factor + exercise, plus the goal's
| change; protein per kg, fat 30%, carbohydrate the rest. A nutritionist's value overrides
| the computed one for their client.
|
| 'refs' name the sources of a rule (config/nutrition_references.php); 'info_only' rules are
| shown but never judge a meal.
|
| Rule types (CriteriaCalculator):
|   energy      the daily calorie target
|   macro       the template's grams of carbs, protein or fat ('macro_rule')
|   per_kg      grams per kg of body weight
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
    | The new calculation (the owner's decisions, Nov 2026): TDEE = BMR × the day's factor
    | (without exercise) + the exercise's extra. App\Services\Nutrition\Criteria\EnergyCalculator.
    |
    | The day without exercise (App\Enums\DailyLife), from FAO/WHO/UNU 2004 Table 5.1:
    |   home      1.40  a day at home worked out the FAO way gives 1.37, under the lowest
    |                   level adults live at (1.40), so 1.40
    |   seated    1.53  FAO's own office worker who "does not exercise"
    |   standing  1.64  FAO's waiter with its hour of exercise taken out: (42.2 − 4.2 + 1.4) ÷ 24
    |   physical  1.80  inside FAO's band for masons and builders (1.70–1.99): the least exact
    | With work on some days only, the days off count as home: (days × factor + rest × 1.40) ÷ 7.
    */
    'daily_life' => [
        'home' => 1.40,
        'seated' => 1.53,
        'standing' => 1.64,
        'physical' => 1.80,
    ],
    'daily_life_refs' => ['fao_energy_2004'],

    /*
    | Exercise: the extra an hour = MET × weight − 'replaced_par' × BMR ÷ 24 (the hour would
    | have been sitting, PAR 1.4 in FAO Table 5.1, already in the day's factor), averaged over
    | the week. "1 MET = 1 kcal/kg/hr" (compendium_2024). Values and codes from the 2024 Adult
    | Compendium of Physical Activities. 'sport_days': training on this many days a week or
    | more brings the sport template. 'max_exercises': rows a person may write (the same sport
    | twice when its minutes differ from day to day).
    */
    'replaced_par' => 1.4,
    'sport_days' => 3,
    'max_exercises' => 5,
    'exercise_refs' => ['compendium_2024', 'who_pa_2020'],
    'exercises' => [
        'walk' => ['label' => 'مشي عادي', 'met' => 3.0, 'code' => '17170'],
        'brisk_walk' => ['label' => 'مشي سريع', 'met' => 4.8, 'code' => '17200'],
        'jog' => ['label' => 'هرولة', 'met' => 7.5, 'code' => '12020'],
        'run' => ['label' => 'جري (نحو 10 كم/س)', 'met' => 9.3, 'code' => '12050'],
        'weights' => ['label' => 'حديد عادي', 'met' => 3.5, 'code' => '02054'],
        'gym' => ['label' => 'نادي عام أو كلاسات', 'met' => 5.5, 'code' => '02060'],
        'weights_hard' => ['label' => 'حديد قوي (بناء أجسام)', 'met' => 6.0, 'code' => '02050'],
        'cardio' => ['label' => 'كارديو متوسط (أوربيتراك)', 'met' => 5.0, 'code' => '02048'],
        'cycling' => ['label' => 'دراجة', 'met' => 7.0, 'code' => '01014'],
        'swim' => ['label' => 'سباحة هادئة', 'met' => 5.8, 'code' => '18240'],
        'swim_fast' => ['label' => 'سباحة سريعة', 'met' => 9.8, 'code' => '18230'],
        'football' => ['label' => 'كرة قدم ودّية', 'met' => 7.0, 'code' => '15610'],
        'football_match' => ['label' => 'كرة قدم (مباراة)', 'met' => 9.5, 'code' => '15605'],
        'basketball' => ['label' => 'كرة سلة', 'met' => 7.5, 'code' => '15055'],
        'boxing' => ['label' => 'ملاكمة على الكيس', 'met' => 5.8, 'code' => '15110'],
    ],

    /* TDEE ÷ BMR outside this range (FAO: what adults sustain) asks the person to check their answers. */
    'pal_range' => [1.40, 2.40],

    /*
    | Calories added to the daily need for each goal (App\Enums\Goal): a 500 kcal deficit
    | (nice_obesity_ng246 1.16.4: most studies used 500 to 800; a normal weight may lose too,
    | told so gently: decision 2a), and +300 to gain, counted like the rest; it has no
    | reference yet, so the sources page calls it our choice (decision 4a).
    */
    'goal_adjustment' => [
        'lose' => -500,
        'maintain' => 0,
        'gain' => 300,
    ],
    'goal_refs' => ['aha_obesity_2013', 'nice_obesity_ng246'],

    /*
    | The nutritionist's nutrition plan (App\Services\Nutrition\Plan, the owner's decisions
    | 1a 2a 3a 4a). It overrides the automatic calories and macros for one client:
    |  - equations: the resting energy formulas offered (App\Enums\EnergyEquation);
    |  - adjustments: the ready-made calorie changes; any value in adjustment_range can be set
    |    with the slider. -750 and +500 are for nutritionists only (3a): people keep goal_adjustment;
    |  - macro_range: what each share of calories may be set to, in %; the three make 100;
    |  - fluids: ml per kg suggested, rounded to fluids_step.
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
    | a block).
    */
    'bmi' => [
        'classes' => [
            ['key' => 'underweight', 'below' => 18.5, 'label' => 'أقل من الطبيعي', 'clinical' => 'نقص الوزن', 'warn' => ['lose']],
            ['key' => 'normal', 'below' => 25, 'label' => 'ضمن الطبيعي', 'clinical' => 'وزن طبيعي', 'warn' => ['lose']],
            ['key' => 'overweight', 'below' => 30, 'label' => 'أعلى من الطبيعي', 'clinical' => 'زيادة وزن'],
            ['key' => 'obese_1', 'below' => 35, 'label' => 'أعلى بكثير من الطبيعي (الدرجة الأولى)', 'clinical' => 'سمنة درجة أولى (خفيفة)', 'warn' => ['gain']],
            ['key' => 'obese_2', 'below' => 40, 'label' => 'أعلى بكثير من الطبيعي (الدرجة الثانية)', 'clinical' => 'سمنة درجة ثانية (متوسطة)', 'warn' => ['gain']],
            ['key' => 'obese_3', 'below' => null, 'label' => 'أعلى بكثير من الطبيعي (الدرجة الثالثة)', 'clinical' => 'سمنة درجة ثالثة (مفرطة)', 'warn' => ['gain']],
        ],
        /* The healthy range, turned into kilograms for the person's height. */
        'healthy' => [18.5, 24.9],
        /* 18.5, 25 and 30 read from WHO; the obesity classes 35 and 40 are not read yet. */
        'refs' => ['who_bmi'],
    ],

    /* Calories per gram, to turn a share of the day into grams. */
    'kcal_per_g' => ['carbs' => 4, 'protein' => 4, 'fat' => 9],

    /*
    | Each criterion: label, unit, bound (min | max | target), scope (day | meal: a meal
    | criterion is not scaled by meal_share), the nutrient it is compared with, the default
    | rule (null: only a nutritionist turns it on), and the range it may be set to.
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
            'nutrient' => 'protein_g', 'kind' => 'guidance', 'refs' => ['dga_2025_protein', 'nasem_amdr'],
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

    /*
    | Sharing the calories (the owner's decisions, Nov 2026): protein first, in grams per kg
    | (dga_2025_protein: "1.2–1.6 grams of protein per kilogram of body weight per day"), fat
    | 30% of the calories (who_fats_2023), carbohydrate the rest. When the carbohydrate falls
    | under 45% (nasem_amdr), the fat comes down 5 points at a time to 20%, never the protein;
    | and the protein never passes 35% of the calories (nasem_amdr).
    */
    'macro_rule' => [
        'fat_pct' => 30,
        'fat_min_pct' => 20,
        'fat_step' => 5,
        'carbs_min_pct' => 45,
        'protein_max_pct' => 35,
    ],
    'protein_refs' => ['dga_2025_protein', 'acsm_2016'],

    /*
    | Carbohydrate for people who train (acsm_2016, Table 2), by the training hours a day on
    | average (hours a week ÷ 7; the owner's decisions 1a 3a). A check on the sport template
    | only: when the carbohydrate is under its level's least, the fat comes down to fat_min_pct
    | to make room; if still short, the person is told, and no calories are added (the statement:
    | otherwise "carbohydrate intake may be chosen to suit energy goals"). The hour boundaries
    | are ours: the statement gives "~1 h per day", "1–3 h/d" and ">4–5 h/d".
    */
    'sport_carbs' => [
        'refs' => ['acsm_2016'],
        'levels' => [
            ['key' => 'light', 'label' => 'خفيف', 'below_hours' => 0.75, 'g_per_kg' => [3, 5]],
            ['key' => 'moderate', 'label' => 'متوسط', 'below_hours' => 1.5, 'g_per_kg' => [5, 7]],
            ['key' => 'high', 'label' => 'عالٍ', 'below_hours' => 4, 'g_per_kg' => [6, 10]],
            ['key' => 'very_high', 'label' => 'عالٍ جداً', 'below_hours' => null, 'g_per_kg' => [8, 12]],
        ],
    ],

    /* The template a goal brings; training on 'sport_days' days a week or more brings 'sport'. */
    'goal_templates' => ['maintain' => 'balanced', 'lose' => 'weight_loss', 'gain' => 'gain'],
    'default_template' => 'balanced',

    /*
    | The four templates: the protein each asks for, in grams per kg ('protein_per_kg': one value,
    | or one per goal). Sport keeps the goal's protein (acsm_2016: 1.2 to 2.0 g/kg/d; decision 2a)
    | and checks its carbohydrate per kg (sport_carbs). A nutritionist may choose any of them.
    */
    'templates' => [
        'balanced' => [
            'label' => 'التغذية المتوازنة', 'group' => 'general',
            'description' => 'للمحافظة على الوزن بأكل متوازن: بروتين 1.2 غ لكل كغ، دهون 30% من السعرات، والباقي كربوهيدرات.',
            'protein_per_kg' => 1.2,
        ],
        'weight_loss' => [
            'label' => 'إنقاص الوزن', 'group' => 'general',
            'description' => 'عجز هادئ في السعرات، وبروتين أعلى (1.6 غ لكل كغ) ليحمي العضل وأنت تنقص.',
            'protein_per_kg' => 1.6,
        ],
        'gain' => [
            'label' => 'زيادة الوزن والعضل', 'group' => 'general',
            'description' => 'سعرات أكثر قليلاً من حرقك، وبروتين 1.6 غ لكل كغ ليُبنى العضل مع التمرين.',
            'protein_per_kg' => 1.6,
        ],
        'sport' => [
            'label' => 'النشاط الرياضي', 'group' => 'general',
            'description' => 'لمن يتمرّن 3 أيام أو أكثر: سعرات تمرينه محسوبة كاملة، والبروتين حسب هدفه، والكربوهيدرات بقدر تمرينه.',
            'protein_per_kg' => ['maintain' => 1.2, 'lose' => 1.6, 'gain' => 1.6],
            'sport_carbs' => true,
        ],
    ],

];
