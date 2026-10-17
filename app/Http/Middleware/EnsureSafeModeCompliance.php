<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Application\SafeMode\Services\MikrotikSafeModeGuard;
use App\Domain\SafeMode\Enums\DestructiveAction;
use App\Models\Router;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSafeModeCompliance
{
    public function __construct(
        protected MikrotikSafeModeGuard $guard
    ) {}

    /**
     * Handle incoming request and verify Safe Mode compliance.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, ?string $explicitAction = null): Response
    {
        /** @var Router|null $router */
        $router = $request->attributes->get('active_router');

        if (! $router instanceof Router) {
            $routerParam = $request->route('router');
            if ($routerParam instanceof Router) {
                $router = $routerParam;
            } elseif (is_numeric($routerParam)) {
                $router = Router::find((int) $routerParam);
            }
        }

        // If no router context, continue
        if (! $router instanceof Router || ! $router->is_safe_mode_enabled) {
            return $next($request);
        }

        // Determine destructive action type
        $action = null;
        if ($explicitAction !== null) {
            $action = DestructiveAction::tryFrom($explicitAction);
        }

        if ($action === null) {
            $path = $request->path();
            if (str_contains($path, '/reboot')) {
                $action = DestructiveAction::SystemReboot;
            } elseif (str_contains($path, '/shutdown')) {
                $action = DestructiveAction::SystemShutdown;
            } elseif (str_contains($path, '/reset')) {
                $action = DestructiveAction::ResetConfiguration;
            } elseif ($request->isMethod('DELETE') && str_contains($path, '/vouchers')) {
                $action = DestructiveAction::BulkDeleteVouchers;
            } elseif ($request->isMethod('DELETE') && str_contains($path, '/ppp/secrets')) {
                $action = DestructiveAction::BulkDeletePppSecrets;
            } elseif ($request->isMethod('DELETE') && str_contains($path, '/ppp/profiles')) {
                $action = DestructiveAction::BulkDeletePppProfiles;
            } elseif ($request->isMethod('DELETE') && str_contains($path, '/routers')) {
                $action = DestructiveAction::RouterDeletion;
            }
        }

        // If action is destructive, enforce guardrail
        if ($action instanceof DestructiveAction) {
            $bypassToken = $request->header('X-SafeMode-Bypass') ?? $request->input('bypass_token');
            $bypassReason = $request->header('X-Bypass-Reason') ?? $request->input('bypass_reason');

            $this->guard->enforce(
                router: $router,
                action: $action,
                endpoint: $request->path(),
                payload: $request->all(),
                bypassToken: is_string($bypassToken) ? $bypassToken : null,
                bypassReason: is_string($bypassReason) ? $bypassReason : null
            );
        }

        return $next($request);
    }
}
