<?php

use App\Http\Controllers\Site\Auth\EmailVerificationController;
use App\Http\Controllers\Site\Auth\GoogleAuthController;
use App\Http\Controllers\Site\Auth\LoginController;
use App\Http\Controllers\Site\Auth\PasswordResetController;
use App\Http\Controllers\Site\Auth\RegisterController;
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
    ];

    foreach ($soon as $name => [$uri, $title]) {
        Route::view($uri, 'site.pages.soon', ['title' => $title])->name($name);
    }

    /*
    | Accounts. Form posts are rate limited per visitor (AppServiceProvider); sign-in also
    | locks one email after 5 wrong passwords (LoginRequest), and each code allows 5 tries.
    */
    Route::middleware('guest')->group(function () {
        Route::get('/register', [RegisterController::class, 'create'])->name('register');
        Route::post('/register', [RegisterController::class, 'store'])->middleware('throttle:auth-forms');

        Route::get('/login', [LoginController::class, 'create'])->name('login');
        Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:auth-forms');

        Route::get('/verify-email', [EmailVerificationController::class, 'show'])->name('verify');
        Route::post('/verify-email', [EmailVerificationController::class, 'store'])->middleware('throttle:auth-forms');
        Route::post('/verify-email/resend', [EmailVerificationController::class, 'resend'])
            ->middleware('throttle:auth-codes')->name('verify.resend');

        Route::get('/forgot-password', [PasswordResetController::class, 'create'])->name('password.request');
        Route::post('/forgot-password', [PasswordResetController::class, 'sendCode'])->middleware('throttle:auth-codes');
        Route::get('/reset-password', [PasswordResetController::class, 'edit'])->name('password.reset');
        Route::post('/reset-password', [PasswordResetController::class, 'update'])->middleware('throttle:auth-forms');

        // "Continue with Google" (GoogleAuthService); a new brand owner or nutritionist
        // gives a WhatsApp number on one extra page.
        Route::get('/auth/google/redirect', [GoogleAuthController::class, 'redirect'])
            ->middleware('throttle:auth-forms')->name('google.redirect');
        Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])
            ->middleware('throttle:auth-forms')->name('google.callback');
        Route::get('/auth/google/whatsapp', [GoogleAuthController::class, 'whatsapp'])->name('google.whatsapp');
        Route::post('/auth/google/whatsapp', [GoogleAuthController::class, 'storeWhatsapp'])->middleware('throttle:auth-forms');
    });

    Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');
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
