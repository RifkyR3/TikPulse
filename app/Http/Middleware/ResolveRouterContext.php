<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Router;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveRouterContext
{
    /**
     * Handle an incoming request and bind active router context.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, bool $required = false): Response
    {
        $routerId = $request->header('X-Router-ID') ?? $request->route('router');

        if ($routerId instanceof Router) {
            $router = $routerId;
        } elseif (! empty($routerId) && is_numeric($routerId)) {
            $router = Router::find((int) $routerId);
        } else {
            $router = null;
        }

        if ($required && ! $router) {
            return response()->json([
                'status' => 'error',
                'code' => 'ROUTER_CONTEXT_REQUIRED',
                'message' => 'Active router context is required for this operation. Please provide the X-Router-ID header.',
            ], 400);
        }

        if ($router) {
            $request->attributes->set('active_router', $router);
        }

        return $next($request);
    }
}
