<?php

namespace App\Providers;

use App\Http\Middleware\InitializeTenancyIfTenantDomain;
use App\Livewire\TenantDatabaseNotifications;
use App\Models\Category;
use App\Observers\CategoryObserver;
use App\Models\Brand;
use App\Models\Food;
use App\Models\FoodNutrientValue;
use App\Models\FoodSourceRecord;
use App\Models\Product;
use App\Models\ProductRecipe;
use App\Models\ProductRecipeIngredient;
use App\Services\Site\PublicCatalogCache;
use Illuminate\Database\Eloquent\Model;
use App\View\Composers\NotificationBellComposer;
use App\View\Composers\ProfileReminderComposer;
use Filament\Notifications\Livewire\DatabaseNotifications;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Stancl\Tenancy\Events\TenancyInitialized;
use Stancl\Tenancy\Events\TenantCreated;


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
    private function watchPublicCatalog(): void
    {
        $models = [Brand::class, Product::class, ProductRecipe::class, ProductRecipeIngredient::class,
            FoodNutrientValue::class, Food::class, FoodSourceRecord::class];

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

        // Register upload/preview routes directly with tenancy middleware
        $this->app->booted(function () {
            Route::post('/livewire/upload-file', [\Livewire\Features\SupportFileUploads\FileUploadController::class, 'handle'])
                ->middleware(['web', InitializeTenancyIfTenantDomain::class])
                ->name('livewire.upload-file');

            Route::get('/livewire/preview-file/{filename}', [\Livewire\Features\SupportFileUploads\FileUploadController::class, 'handle'])
                ->middleware(['web', InitializeTenancyIfTenantDomain::class])
                ->name('livewire.preview-file');
        });

        Event::listen(TenancyInitialized::class, function ($event) {
            $tenantId = $event->tenancy->tenant->id;
            $currentDomain = request()->getSchemeAndHttpHost();

            app('filesystem')->forgetDisk('tenant_uploads');

            config([
                'filesystems.disks.tenant_uploads.root' =>
                    storage_path("tenant{$tenantId}/app/public/uploads"),
                'filesystems.disks.tenant_uploads.url' =>
                    $currentDomain . '/tenant-image/' . $tenantId,
            ]);

            app('filesystem')->forgetDisk('tenant_uploads');
        });

        Event::listen(TenantCreated::class, function (TenantCreated $event) {
            $tenantId = $event->tenant->id;

            $directories = [
                storage_path("tenant{$tenantId}/framework/cache"),
                storage_path("tenant{$tenantId}/framework/sessions"),
                storage_path("tenant{$tenantId}/framework/views"),
                storage_path("tenant{$tenantId}/logs"),
                storage_path("tenant{$tenantId}/app/public/uploads/livewire-tmp"),
                storage_path("tenant{$tenantId}/app/public/uploads/categories/logo"),
            ];

            foreach ($directories as $path) {
                if (!file_exists($path)) {
                    mkdir($path, 0775, true);
                }
            }

            if (PHP_OS_FAMILY !== 'Windows') {
                $symlinkPath = public_path("storage/tenant{$tenantId}");
                $targetPath = storage_path("tenant{$tenantId}/app/public");

                if (!file_exists($symlinkPath)) {
                    symlink($targetPath, $symlinkPath);
                }
            }
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
    }
}
