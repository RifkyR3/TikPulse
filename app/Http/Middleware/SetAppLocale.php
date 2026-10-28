<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetAppLocale
{
    /**
     * Handle an incoming request and set application locale.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->header('X-Locale')
            ?? ($request->hasSession() ? $request->session()->get('locale') : null)
            ?? $request->user()?->preferred_locale
            ?? config('app.locale', 'en');

        if (in_array($locale, ['en', 'id'], true)) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}
