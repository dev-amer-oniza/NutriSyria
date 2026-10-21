<?php

/*
| The criteria every meal is judged against, and how each one is worked out by default
| from a person's profile. Approved by the project owner (2026-10). Changing a number
| here changes it for everyone who has not set their own value; adding a criterion is
| one entry in 'criteria'. A person can override any value, and their nutritionist's
| value overrides both (App\Services\Nutrition\Criteria\PersonalCriteria).
|
| Default rule types (CriteriaCalculator):
|   energy      the daily calorie target (Mifflin-St Jeor x activity + goal)
|   per_kg      grams per kg of body weight; 'by_goal' or a single 'value'
|   energy_pct  a share of the daily calories, turned into grams with 'kcal_per_g'
|   per_1000    grams per 1000 kcal of the daily target
|   fixed       a fixed amount
*/

return [

    /* Personal calculations start at this age; the formula is for adults. */
    'min_age' => 18,

    /* One restaurant meal = this share of the day (the main meal). */
    'meal_share' => 0.30,

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

    /*
    | Each criterion: label, unit, bound (min | max | target), scope (day | meal: a meal
    | criterion is not scaled by meal_share), the nutrient code it is compared with, the
    | default rule, and the range a person may set it to.
    */
    'criteria' => [
        'energy_kcal' => [
            'label' => 'السعرات', 'unit' => 'سعرة', 'bound' => 'target', 'scope' => 'day',
            'nutrient' => 'energy_kcal',
            'default' => ['type' => 'energy'],
            'range' => [800, 6000],
        ],
        'protein_g' => [
            'label' => 'البروتين', 'unit' => 'غ', 'bound' => 'min', 'scope' => 'day',
            'nutrient' => 'protein_g',
            'default' => ['type' => 'per_kg', 'by_goal' => ['lose' => 1.2, 'maintain' => 1.0, 'gain' => 1.6]],
            'range' => [20, 400],
        ],
        'fat_g' => [
            'label' => 'الدهون', 'unit' => 'غ', 'bound' => 'max', 'scope' => 'day',
            'nutrient' => 'fat_g',
            'default' => ['type' => 'energy_pct', 'pct' => 35, 'kcal_per_g' => 9],
            'range' => [10, 300],
        ],
        'fat_sat_g' => [
            'label' => 'الدهون المشبعة', 'unit' => 'غ', 'bound' => 'max', 'scope' => 'day',
            'nutrient' => 'fat_sat_g',
            'default' => ['type' => 'energy_pct', 'pct' => 10, 'kcal_per_g' => 9],
            'range' => [5, 100],
        ],
        'carbohydrate_g' => [
            'label' => 'الكربوهيدرات', 'unit' => 'غ', 'bound' => 'max', 'scope' => 'day',
            'nutrient' => 'carbohydrate_g',
            'default' => ['type' => 'energy_pct', 'pct' => 65, 'kcal_per_g' => 4],
            'range' => [20, 900],
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
        // Only active for some conditions (see below): no default rule.
        'meal_carbohydrate_g' => [
            'label' => 'كربوهيدرات الوجبة', 'unit' => 'غ', 'bound' => 'max', 'scope' => 'meal',
            'nutrient' => 'carbohydrate_g',
            'default' => null,
            'range' => [10, 200],
        ],
    ],

    /*
    | Health conditions (App\Enums\HealthCondition) change criteria. When several set the
    | same one, the stricter wins: the lower ceiling, the higher floor. 'bound' may turn a
    | floor into a ceiling (protein for the kidneys). 'no_deficit' keeps the weight-loss
    | deficit off; 'see_nutritionist' adds a clear advice to see one.
    */
    'conditions' => [
        'hypertension' => [
            'criteria' => ['sodium_mg' => ['type' => 'fixed', 'value' => 1500]],
        ],
        'diabetes' => [
            'criteria' => [
                'meal_carbohydrate_g' => ['type' => 'fixed', 'value' => 60],
                'sugars_g' => ['type' => 'energy_pct', 'pct' => 5, 'kcal_per_g' => 4],
            ],
        ],
        'high_cholesterol' => [
            'criteria' => ['fat_sat_g' => ['type' => 'energy_pct', 'pct' => 6, 'kcal_per_g' => 9]],
        ],
        'kidney' => [
            'criteria' => [
                'protein_g' => ['type' => 'per_kg', 'value' => 0.8, 'bound' => 'max'],
                'sodium_mg' => ['type' => 'fixed', 'value' => 2000],
            ],
            'see_nutritionist' => 'أمراض الكلى تحتاج أرقاماً شخصية (البوتاسيوم والفوسفور) يحددها لك أخصائي.',
        ],
        'pregnant_or_breastfeeding' => [
            'criteria' => [],
            'no_deficit' => true,
            'see_nutritionist' => 'الحمل والرضاعة يغيّران احتياجك، فلا نقترح إنقاص الوزن. راجعي أخصائية تغذية.',
        ],
    ],

];
