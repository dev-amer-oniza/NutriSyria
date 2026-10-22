<?php

namespace App\Http\Middleware;

use App\Services\Consultations\ConsultationService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * While a nutritionist is linked, the person's profile, health record, template and criteria
 * are theirs to manage (the owner's decision): every route that changes them, and the set-up
 * forms, send the person back with the reason. Ending the link opens them again.
 */
class EnsureProfileUnlocked
{
    public const MESSAGE = 'بياناتك ومعاييرك بإدارة أخصائيّك ما دام الارتباط قائماً. تواصل معه لتعديلها، أو أنهِ الارتباط من صفحة "أخصائيّي".';

    public function __construct(private readonly ConsultationService $consultations) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && $this->consultations->isManaged($request->user())) {
            return ($request->isMethod('GET') ? redirect()->route('site.criteria') : back())
                ->with('error', self::MESSAGE);
        }

        return $next($request);
    }
}
