<?php

declare(strict_types=1);

use App\Models\Tenant;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Modules\Restaurants\Models\Category;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

// -------------------------------------------------------
// File serving route — OUTSIDE tenant middleware group
// so it works on localhost (central domain) too
// -------------------------------------------------------


// -------------------------------------------------------
// Tenant routes — protected by tenancy middleware
// -------------------------------------------------------
Route::middleware([
    'web',
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
])->group(function () {
    // Add this AT THE VERY TOP of routes/tenant.php, before everything
    Route::get('/storage/tenant{tenantId}/uploads/{path}', function (string $tenantId, string $path) {
        return response('ROUTE HIT - Laravel is handling this', 200);
    })->where('path', '.*');

    Route::get('/', function () {
        return 'This is your multi-tenant application. The id of the current tenant is ' . tenant('id');
    });

    Route::get('/debug-livewire-route', function () {
        return collect(\Route::getRoutes())->filter(function ($route) {
            return str_contains($route->uri(), 'livewire');
        })->map(function ($route) {
            return [
                'uri' => $route->uri(),
                'middleware' => $route->middleware(),
                'action' => $route->getActionName(),
            ];
        })->values();
    });

    Route::middleware(['web'])->get('/tenant-image/{tenantId}/{path}', function (string $tenantId, string $path) {
        

        $tenant = Tenant::findOrFail($tenantId);
        tenancy()->initialize($tenant);


        $disk = Storage::disk('tenant_uploads');

        if (!$disk->exists($path)) {
            abort(404);
        }

        Log::info( $disk->path($path));

        return response()->file(
            $disk->path($path),
            ['Content-Type' => $disk->mimeType($path)]
        );
    })->where('path', '.*');
});