<?php

namespace App\Providers;

use App\Http\Middleware\InitializeTenancyIfTenantDomain;
use App\Livewire\TenantDatabaseNotifications;
use App\Models\Category;
use App\Observers\CategoryObserver;
use Filament\Notifications\Livewire\DatabaseNotifications;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
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
    public function boot(): void
    {
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
}
