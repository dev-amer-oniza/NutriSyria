<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Symfony\Component\HttpFoundation\Response;

class InitializeTenancyIfTenantDomain
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next)
    {
        $centralDomains = config('tenancy.central_domains', []);
        $host = $request->getHost();

        // Only initialize tenancy if NOT on a central domain
        if (!in_array($host, $centralDomains)) {
             \Log::info('Initializing tenancy for upload', ['host' => $host]);
            return app(InitializeTenancyByDomain::class)->handle($request, $next);
        }

        return $next($request);
    }
}
