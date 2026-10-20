<?php

use App\Http\Controllers\Site\HomeController;
use Illuminate\Support\Facades\Route;

/*
| Public site. Pages that are not built yet share one placeholder view
| until they get their own controller.
*/
Route::get('/', HomeController::class)->name('home');

Route::name('site.')->group(function () {
    $soon = [
        'meals' => ['/meals', 'الوجبات'],
        'brands' => ['/restaurants', 'المطاعم'],
        'method' => ['/method', 'منهجية التحقق'],
        'partners' => ['/for-restaurants', 'للمطاعم'],
        'goals' => ['/goals', 'الأهداف الصحية'],
        'sources' => ['/sources', 'المصادر'],
        'about' => ['/about', 'من نحن'],
        'contact' => ['/contact', 'تواصل معنا'],
        'privacy' => ['/privacy', 'سياسة الخصوصية'],
        'login' => ['/login', 'تسجيل الدخول'],
        'register' => ['/register', 'أنشئ حسابك'],
    ];

    foreach ($soon as $name => [$uri, $title]) {
        Route::view($uri, 'site.pages.soon', ['title' => $title])->name($name);
    }
});

Route::get('/admin/import-templates/{type}', function (string $type) {
    $templates = [
        'records'         => ['food_id', 'food_name_ar', 'food_name_en', 'category_path', 'food_form_code', 'data_source_code', 'external_ref', 'notes'],
        'nutrient_values' => ['data_source_code', 'external_ref', 'nutrient_code', 'amount_per_100g', 'unit', 'value_qualifier', 'method', 'confidence_level', 'sample_count', 'min_amount', 'max_amount', 'notes'],
        'portions'        => ['data_source_code', 'external_ref', 'measure_unit_label', 'amount', 'gram_weight', 'description', 'basis'],
    ];

    abort_unless(isset($templates[$type]), 404);

    $csv = "\xEF\xBB\xBF" . implode(',', $templates[$type]) . "\r\n";

    return response($csv, 200, [
        'Content-Type'        => 'text/csv; charset=UTF-8',
        'Content-Disposition' => "attachment; filename={$type}.csv",
    ]);
})->middleware('web')->name('import.template');
