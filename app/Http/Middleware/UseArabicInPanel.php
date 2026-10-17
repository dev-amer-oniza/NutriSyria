<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The admin panel is Arabic and right-to-left whatever APP_LOCALE says: Filament takes the
 * direction and its own labels from the current locale. Other routes keep the app's locale.
 */
class UseArabicInPanel
{
    public function handle(Request $request, Closure $next): Response
    {
        app()->setLocale('ar');

        return $next($request);
    }
}
