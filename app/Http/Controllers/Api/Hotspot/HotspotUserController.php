<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Hotspot;

use App\Application\SafeMode\Services\MikrotikSafeModeGuard;
use App\Domain\SafeMode\Enums\DestructiveAction;
use App\Http\Controllers\Controller;
use App\Infrastructure\Network\Mikrotik\Services\HotspotVoucherService;
use App\Models\Router;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HotspotUserController extends Controller
{
    public function __construct(
        protected HotspotVoucherService $hotspotService,
        protected MikrotikSafeModeGuard $safeModeGuard
    ) {}

    /**
     * List all Hotspot Users directly from MikroTik router (/ip/hotspot/user).
     */
    public function index(Request $request, Router $router): JsonResponse
    {
        $connService = $this->hotspotService->getConnectionService();

        if ($connService->isCircuitOpen($router)) {
            return response()->json([
                'status' => 'error',
                'message' => "Router {$router->name} is offline. Polling paused to prevent timeouts.",
                'data' => [],
                'total' => 0,
                'is_polling_paused' => true,
            ], 503);
        }

        try {
            $fresh = $request->boolean('fresh', false);
            $users = $this->hotspotService->getHotspotUsers($router, $fresh);

            return response()->json([
                'status' => 'success',
                'data' => $users,
                'total' => count($users),
            ]);
        } catch (Exception $e) {
            $failures = $connService->recordFailure($router);

            return response()->json([
                'status' => 'error',
                'message' => 'Unable to query MikroTik hotspot users: '.$e->getMessage(),
                'data' => [],
                'total' => 0,
                'consecutive_failures' => $failures,
                'is_polling_paused' => $failures >= $connService::MAX_RETRIES,
            ], 502);
        }
    }

    /**
     * Add a new hotspot user directly to MikroTik.
     */
    public function store(Request $request, Router $router): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'password' => ['required', 'string', 'max:100'],
            'profile' => ['required', 'string', 'max:100'],
            'comment' => ['nullable', 'string', 'max:255'],
            'time_limit' => ['nullable', 'string', 'max:50'],
            'data_limit' => ['nullable', 'integer', 'min:0'],
        ]);

        try {
            $this->hotspotService->addHotspotUser($router, $validated);

            return response()->json([
                'status' => 'success',
                'message' => "Hotspot user '{$validated['name']}' added to MikroTik successfully.",
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to add hotspot user: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove a hotspot user from MikroTik (Protected by Safe Mode guardrail).
     */
    public function destroy(Request $request, Router $router, string $username): JsonResponse
    {
        $bypassToken = $request->header('X-SafeMode-Bypass') ?? $request->input('bypass_token');

        $this->safeModeGuard->enforce(
            router: $router,
            action: DestructiveAction::BulkDeleteVouchers,
            endpoint: $request->path(),
            payload: ['username' => $username],
            bypassToken: is_string($bypassToken) ? $bypassToken : null
        );

        try {
            $deleted = $this->hotspotService->removeHotspotUser(
                router: $router,
                username: $username,
                bypassToken: is_string($bypassToken) ? $bypassToken : null,
                enforceGuard: false
            );

            if (! $deleted) {
                return response()->json([
                    'status' => 'error',
                    'message' => "Hotspot user '{$username}' not found on router.",
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'message' => "Hotspot user '{$username}' removed from MikroTik.",
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to remove hotspot user: '.$e->getMessage(),
            ], 500);
        }
    }
}
