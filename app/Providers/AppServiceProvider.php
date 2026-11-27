<?php

namespace App\Providers;

use App\Auth\Access;
use App\Models\Brand;
use App\Models\Food;
use App\Models\FoodNutrientValue;
use App\Models\FoodSourceRecord;
use App\Models\Permission;
use App\Models\Preparation;
use App\Models\PreparationIngredient;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductRecipe;
use App\Models\ProductRecipeIngredient;
use App\Models\Role;
use App\Services\Nutrition\ProductNotices;
use App\Services\Site\PublicCatalogCache;
use App\View\Composers\NotificationBellComposer;
use App\View\Composers\ProfileReminderComposer;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    /**
     * What the public site shows is cached (PublicCatalogCache): any change to it clears the
     * cache, so the next visitor sees it at once.
     */
    /** A restaurant's recipe that now waits for approval tells the nutritionists (ProductNotices, once per review). */
    private function watchRecipes(): void
    {
        $notices = fn (?int $productId) => $productId && app(ProductNotices::class)->recipeChanged($productId);

        foreach (['saved', 'deleted'] as $event) {
            ProductRecipe::$event(fn (ProductRecipe $recipe) => $notices($recipe->product_id));
            ProductRecipeIngredient::$event(fn (ProductRecipeIngredient $line) => $notices(
                ProductRecipe::query()->whereKey($line->product_recipe_id)->value('product_id')));

            // A preparation changed: every product line using it is touched, so those products
            // read "تغيّرت الوصفة بعد الاعتماد" and go back to the nutritionist (the owner's request).
            Preparation::$event(fn (Preparation $prep) => self::touchLinesOf($prep->getKey()));
            PreparationIngredient::$event(fn (PreparationIngredient $line) => self::touchLinesOf($line->preparation_id));
        }
    }

    private static function touchLinesOf(?int $preparationId): void
    {
        if ($preparationId) {
            ProductRecipeIngredient::query()->where('preparation_id', $preparationId)->get()->each->touch();
        }
    }

    private function watchPublicCatalog(): void
    {
        $models = [Brand::class, Product::class, ProductRecipe::class, ProductRecipeIngredient::class,
            FoodNutrientValue::class, Food::class, FoodSourceRecord::class, ProductCategory::class, Preparation::class, PreparationIngredient::class];

        foreach ($models as $model) {
            foreach (['saved', 'deleted'] as $event) {
                $model::$event(fn () => app(PublicCatalogCache::class)->flush());
            }
        }
    }

    public function boot(): void
    {
        $this->configureRateLimits();
        View::composer('site.layouts.app', ProfileReminderComposer::class);
        View::composer('site.partials.header', NotificationBellComposer::class);
        $this->watchPublicCatalog();
        $this->watchRecipes();
        foreach ([Role::class, Permission::class] as $model) {
            $model::saved(fn () => Access::forget());
            $model::deleted(fn () => Access::forget());
        }

        // N+1 guard (the owner's decision 4a): outside production, a relation loaded one row
        // at a time inside a list is an error, so a slow page is caught before it ships.
        Model::preventLazyLoading(! $this->app->isProduction());

        Livewire::setUpdateRoute(function ($handle) {
            return Route::post('/livewire/update', $handle)
                ->middleware(['web']);
        });

        Livewire::setScriptRoute(function ($handle) {
            return Route::get('/livewire/livewire.js', $handle)
                ->middleware(['web']);
        });
    }

    /**
     * Per-visitor limits on the site's account forms. A blocked visitor is sent back to the
     * form with a message instead of a bare 429 page.
     */
    private function configureRateLimits(): void
    {
        $tooMany = fn () => back()->withInput(request()->except('password'))
            ->with('error', 'طلبات كثيرة خلال وقت قصير. انتظر دقيقة ثم حاول مجدداً.');

        RateLimiter::for('auth-forms', fn (Request $request) => Limit::perMinute(10)->by($request->ip())->response($tooMany));
        RateLimiter::for('auth-codes', fn (Request $request) => Limit::perMinute(3)->by($request->ip())->response($tooMany));
        // "يومي": logging a meal is a small write; 30 a minute is far more than anyone eats.
        RateLimiter::for('daily', fn (Request $request) => Limit::perMinute(30)->by($request->user()?->id ?: $request->ip())->response($tooMany));
        // "اطبخها على طريقتك": each analysis runs the full calculation, so a visitor gets 20 a minute.
        RateLimiter::for('recipe-analyze', fn (Request $request) => Limit::perMinute(20)->by($request->user()?->id ?: $request->ip())
            ->response(fn () => response()->json(['message' => 'حلّلت كثيراً خلال دقيقة. انتظر قليلاً ثم حاول.'], 429)));
    }
}
