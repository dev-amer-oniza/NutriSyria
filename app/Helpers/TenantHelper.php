<?php

namespace App\Helpers;

use App\Models\Tenant;
use Stancl\Tenancy\Facades\Tenancy;

class TenantHelper
{
    public static function cloudinaryFolder(string $subfolder = ''): string
    {
        $tenant = Tenant::find(tenant('id'));
        $base = $tenant ? "tenants/{$tenant->name}" : "central";

        return $subfolder ? "{$base}/{$subfolder}" : $base;
    }
}