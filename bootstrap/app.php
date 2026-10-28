<?php

use App\Http\Middleware\AuthorizeModuleAccess;
use App\Http\Middleware\EnsureSafeModeCompliance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\ResolveRouterContext;
use App\Http\Middleware\SetAppLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state', 'tikpulse_token']);

        $middleware->alias([
            'router.context' => ResolveRouterContext::class,
            'safe.mode' => EnsureSafeModeCompliance::class,
            'module.access' => AuthorizeModuleAccess::class,
        ]);

        $middleware->web(append: [
            SetAppLocale::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->api(append: [
            SetAppLocale::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
