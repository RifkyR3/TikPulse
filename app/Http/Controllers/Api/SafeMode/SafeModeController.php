<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\SafeMode;

use App\Application\SafeMode\Services\MikrotikSafeModeGuard;
use App\Http\Controllers\Controller;
use App\Http\Requests\SafeMode\ToggleSafeModeRequest;
use App\Http\Requests\SafeMode\UnlockSafeModeRequest;
use App\Http\Resources\SafeModeStatusResource;
use App\Models\Router;
use App\Models\SafeModeAuditLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class SafeModeController extends Controller
{
    public function __construct(
        protected MikrotikSafeModeGuard $guard
    ) {}

    /**
     * Get Safe Mode guardrail status for specified router.
     */
    public function status(Request $request, Router $router): JsonResponse
    {
        $token = $request->header('X-SafeMode-Bypass') ?? $request->query('bypass_token');
        $isBypassed = $this->guard->isBypassTokenValid($router, is_string($token) ? $token : null);

        return response()->json([
            'status' => 'success',
            'data' => new SafeModeStatusResource(
                resource: $router,
                isBypassed: $isBypassed,
                remainingSeconds: $isBypassed ? 900 : null
            ),
        ]);
    }

    /**
     * Unlock Safe Mode temporarily via 2-Step Verification (Password + "UNLOCK" verification).
     */
    public function unlock(UnlockSafeModeRequest $request, Router $router): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        // Step 1: Verify current user password
        if (! Hash::check((string) $request->input('password'), $user->password)) {
            throw ValidationException::withMessages([
                'password' => ['The provided password does not match our records.'],
            ]);
        }

        // Step 2: Issue 15-minute temporary bypass token
        $durationMinutes = 15;
        $token = $this->guard->createBypassToken($router, $user->id, $durationMinutes);

        // Audit log the unlock event
        SafeModeAuditLog::create([
            'router_id' => $router->id,
            'user_id' => $user->id,
            'command_type' => 'safe_mode_unlocked',
            'target_endpoint' => $request->path(),
            'payload' => [
                'reason' => $request->input('reason', 'Two-step manual unlock'),
                'duration_minutes' => $durationMinutes,
            ],
            'is_blocked' => false,
            'bypass_reason' => 'Admin verified two-step unlock sequence.',
            'client_ip' => $request->ip() ?? '127.0.0.1',
            'created_at' => Carbon::now(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Safe Mode temporarily unlocked for 15 minutes.',
            'data' => [
                'router_id' => $router->id,
                'bypass_token' => $token,
                'expires_in_seconds' => $durationMinutes * 60,
                'expires_at' => Carbon::now()->addMinutes($durationMinutes)->toIso8601String(),
            ],
        ]);
    }

    /**
     * Lock router again immediately (revoke bypass token).
     */
    public function lock(Request $request, Router $router): JsonResponse
    {
        $token = $request->header('X-SafeMode-Bypass') ?? $request->input('bypass_token');

        if (is_string($token)) {
            $this->guard->revokeBypassToken($router, $token);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Safe Mode re-locked. Destructive commands are protected.',
            'data' => new SafeModeStatusResource($router, false, null),
        ]);
    }

    /**
     * Toggle router persistent Safe Mode flag.
     */
    public function toggle(ToggleSafeModeRequest $request, Router $router): JsonResponse
    {
        $enabled = (bool) $request->input('enabled');

        if (! $enabled) {
            /** @var User $user */
            $user = $request->user();
            if (! Hash::check((string) $request->input('password'), $user->password)) {
                throw ValidationException::withMessages([
                    'password' => ['Admin password required to disable Safe Mode.'],
                ]);
            }
        }

        $router->update([
            'is_safe_mode_enabled' => $enabled,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => $enabled ? 'Safe Mode enabled permanently.' : 'Safe Mode disabled.',
            'data' => new SafeModeStatusResource($router, false, null),
        ]);
    }

    /**
     * Get paginated audit logs for this router.
     */
    public function auditLogs(Router $router): JsonResponse
    {
        $logs = SafeModeAuditLog::with('user:id,name,email')
            ->where('router_id', $router->id)
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json([
            'status' => 'success',
            'data' => $logs,
        ]);
    }

    /**
     * Get paginated audit logs across all routers (with optional filters).
     */
    public function allAuditLogs(Request $request): JsonResponse
    {
        $query = SafeModeAuditLog::with(['user:id,name,email', 'router:id,name,host,api_port'])
            ->orderByDesc('created_at');

        if ($request->filled('router_id')) {
            $query->where('router_id', $request->query('router_id'));
        }

        if ($request->filled('blocked_only') && $request->boolean('blocked_only')) {
            $query->where('is_blocked', true);
        }

        $logs = $query->paginate(25);

        return response()->json([
            'status' => 'success',
            'data' => $logs,
        ]);
    }
}
