<?php

/*
| The criteria every meal is judged against, and how each one is worked out by default
| from a person's profile (App\Services\Nutrition\Criteria).
|
| The platform serves healthy adults and people who train, with their allergies; health
| conditions were removed (the owner's decision, Nov 2026; the old work is in main's history
| at b76d834). Daily calories: Mifflin-St Jeor x activity, plus the goal's change; the
| template shares them between carbohydrate, protein and fat. A nutritionist's value
| overrides the computed one for their client.
|
| 'refs' name the sources of a rule (config/nutrition_references.php); 'info_only' rules are
| shown but never judge a meal.
|
| Rule types (CriteriaCalculator):
|   energy      the daily calorie target
|   macro       the template's share of calories for carbs, protein or fat, in grams
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
    | Calories added to the daily need for each goal (App\Enums\Goal): a 500 kcal deficit
    | (a normal weight may lose too, told so gently: the owner's decision 2a), and +300 to
    | gain, counted like the rest; it has no reference yet, so the sources page calls it our
    | choice (decision 4a).
    */
    'goal_adjustment' => [
        'lose' => -500,
        'maintain' => 0,
        'gain' => 300,
    ],
    'goal_refs' => ['aha_obesity_2013'],

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

    /* The template everyone starts from. */
    'default_template' => 'balanced',

    /*
    | Diet templates: macros in % of calories (carbs / protein / fat). The four new ones
    | (balanced, weight loss, gain and muscle, sport) come with the new calculation.
    */
    'templates' => [
        'balanced' => [
            'label' => 'المتوازن', 'group' => 'general', 'source' => 'NASEM · WHO',
            'refs' => ['nasem_amdr', 'who_fats_2023'],
            'description' => 'توزيع للبالغين الأصحاء داخل المجالات المرجعية: كربوهيدرات 50%، بروتين 20%، دهون 30%.',
            'macros' => ['carbs' => 50, 'protein' => 20, 'fat' => 30],
        ],
    ],

];
