<?php

/*
| Import vocabulary and quality-check thresholds.
| Keep the lists below in sync with your PHP enums (FoodSourceStatus, NutrientValueMethod,
| ConfidenceLevel, PortionBasis): the database stores plain strings, and this file is what
| the importer validates against. Thresholds are defaults, tune them with your nutritionist.
*/
return [
    'methods' => ['imported', 'estimated', 'measured', 'laboratory', 'calculated'],
    'confidence_levels' => ['calculated', 'reference', 'local_reference', 'measured', 'reviewed', 'verified'],
    'portion_bases' => ['reference', 'usda', 'measured', 'estimated', 'restaurant_measured'],
    'value_qualifiers' => ['trace', 'below_lod'],

    'default_confidence' => 'reference',
    'default_portion_basis' => 'reference',

    'checks' => [
        // water + protein + fat + carbohydrate + ash should be close to 100 g per 100 g
        'proximate_sum_tolerance_g' => 5,
        // reported kcal vs 4*protein + 9*fat + 4*carbohydrate
        'atwater_tolerance_pct' => 20,
        'atwater_floor_kcal' => 10,
        // rounding slack for "part must not exceed whole" checks (fat fractions, sugars, fibre)
        'rounding_tolerance_g' => 0.5,
    ],

    'max_issues_reported' => 50,
    'max_changed_values_logged' => 200,
];
