<?php

use App\Http\Controllers\Site\Account\ConsultationController;
use App\Http\Controllers\Site\Account\CriteriaController;
use App\Http\Controllers\Site\Account\NotificationController;
use App\Http\Controllers\Site\Account\OnboardingController;
use App\Http\Controllers\Site\Auth\EmailVerificationController;
use App\Http\Controllers\Site\Auth\GoogleAuthController;
use App\Http\Controllers\Site\Auth\LoginController;
use App\Http\Controllers\Site\Auth\PasswordResetController;
use App\Http\Controllers\Site\Auth\RegisterController;
use App\Http\Controllers\Site\HomeController;
use App\Http\Controllers\Site\MealController;
use App\Http\Controllers\Site\PartnersController;
use App\Http\Controllers\Site\RestaurantController;
use App\Http\Controllers\Site\NutritionistController;
use Illuminate\Support\Facades\Route;

/*
| Public site. Pages that are not built yet share one placeholder view
| until they get their own controller.
*/
Route::get('/', HomeController::class)->name('home');

Route::name('site.')->group(function () {
    $soon = [
        'method' => ['/method', 'منهجية التحقق'],
        'goals' => ['/goals', 'الأهداف الصحية'],
        'sources' => ['/sources', 'المصادر'],
        'about' => ['/about', 'من نحن'],
        'contact' => ['/contact', 'تواصل معنا'],
        'privacy' => ['/privacy', 'سياسة الخصوصية'],
    ];

    foreach ($soon as $name => [$uri, $title]) {
        Route::view($uri, 'site.pages.soon', ['title' => $title])->name($name);
    }

    // "For restaurants": why and how a restaurant joins.
    Route::get('/for-restaurants', PartnersController::class)->name('partners');

    // The restaurants (active brands), each with its details and menu (RestaurantDirectory).
    Route::get('/restaurants', [RestaurantController::class, 'index'])->name('brands');
    Route::get('/restaurants/{restaurant}', [RestaurantController::class, 'show'])->whereNumber('restaurant')->name('brands.show');

    // The approved meals, each judged for the signed-in person (MealFitService).
    Route::get('/meals', [MealController::class, 'index'])->name('meals');
    Route::get('/meals/{meal}', [MealController::class, 'show'])->whereNumber('meal')->name('meals.show');

    // The nutritionist directory (only listed profiles: NutritionistProfile::isListed).
    Route::get('/nutritionists', [NutritionistController::class, 'index'])->name('nutritionists');
    Route::get('/nutritionists/{profile}', [NutritionistController::class, 'show'])->whereNumber('profile')->name('nutritionists.show');

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

    /*
    | The signed-in person's profile: the three-step set-up, the daily needs it gives, and
    | "my criteria" (config/nutrition_criteria.php, App\Services\Nutrition\Criteria).
    */
    Route::middleware('auth')->group(function () {
        Route::get('/onboarding/done', [OnboardingController::class, 'done'])->name('onboarding.done');
        Route::get('/account/criteria', [CriteriaController::class, 'index'])->name('criteria');

        // Everything that changes the profile or criteria is locked while a nutritionist is linked.
        Route::middleware('profile.unlocked')->group(function () {
            Route::get('/onboarding/{step}', [OnboardingController::class, 'show'])->name('onboarding.show');
            Route::post('/onboarding/body', [OnboardingController::class, 'storeBody'])->name('onboarding.body');
            Route::post('/onboarding/activity', [OnboardingController::class, 'storeActivity'])->name('onboarding.activity');
            Route::post('/onboarding/health', [OnboardingController::class, 'storeHealth'])->name('onboarding.health');

            $criteria = array_keys(config('nutrition_criteria.criteria'));
            Route::put('/account/criteria/{criterion}', [CriteriaController::class, 'update'])
                ->whereIn('criterion', $criteria)->name('criteria.update');
            Route::delete('/account/criteria/{criterion}', [CriteriaController::class, 'reset'])
                ->whereIn('criterion', $criteria)->name('criteria.reset');
            Route::put('/account/template', [CriteriaController::class, 'chooseTemplate'])->name('template.update');
            Route::delete('/account/template', [CriteriaController::class, 'resetTemplate'])->name('template.reset');
            Route::delete('/account/health', [CriteriaController::class, 'destroyHealth'])->name('health.destroy');
        });

        // The bell (SiteNotifications): polled by the header, a notice opens its page.
        Route::get('/account/notifications', [NotificationController::class, 'index'])->name('notifications');
        Route::get('/account/notifications/{notification}', [NotificationController::class, 'open'])
            ->whereUuid('notification')->name('notifications.open');
        Route::post('/account/notifications/read', [NotificationController::class, 'readAll'])->name('notifications.read');

        // Booking a nutritionist (ConsultationService): one request or link at a time.
        Route::get('/nutritionists/{profile}/request', [ConsultationController::class, 'create'])
            ->whereNumber('profile')->name('consultations.create');
        Route::post('/nutritionists/{profile}/request', [ConsultationController::class, 'store'])
            ->whereNumber('profile')->middleware('throttle:auth-forms');
        Route::get('/account/nutritionist', [ConsultationController::class, 'show'])->name('my-nutritionist');
        Route::post('/account/consultations/{consultation}/cancel', [ConsultationController::class, 'cancel'])
            ->whereNumber('consultation')->name('consultations.cancel');
        Route::post('/account/consultations/{consultation}/end', [ConsultationController::class, 'end'])
            ->whereNumber('consultation')->name('consultations.end');
    });
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
