<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthorizeModuleAccess
{
    /**
     * Handle an incoming request to verify module access permissions.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $moduleKey, string $action = 'read'): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'status' => 'error',
                'code' => 'UNAUTHENTICATED',
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if (! $user->hasModulePermission($moduleKey, $action)) {
            return response()->json([
                'status' => 'error',
                'code' => 'MODULE_ACCESS_DENIED',
                'message' => "You do not have permission to {$action} the '{$moduleKey}' module.",
            ], 403);
        }

        return $next($request);
    }
}
