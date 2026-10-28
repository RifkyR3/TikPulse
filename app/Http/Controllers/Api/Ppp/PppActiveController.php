<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Ppp;

use App\Http\Controllers\Controller;
use App\Infrastructure\Network\Mikrotik\Services\PppService;
use App\Models\Router;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PppActiveController extends Controller
{
    public function __construct(
        protected PppService $pppService
    ) {}

    /**
     * List live active PPP sessions for the specified router.
     */
    public function index(Request $request, Router $router): JsonResponse
    {
        $connService = $this->pppService->getConnectionService();

        if ($connService->isCircuitOpen($router)) {
            $failures = $connService->getConsecutiveFailures($router);

            return response()->json([
                'status' => 'offline',
                'code' => 'ROUTER_OFFLINE',
                'message' => "Router is offline. Polling paused after {$failures} consecutive failed attempts.",
                'data' => [],
                'total_active' => 0,
                'is_polling_paused' => true,
                'consecutive_failures' => $failures,
            ], 503);
        }

        try {
            $fresh = $request->boolean('fresh', false);
            $connections = $this->pppService->getActiveConnections($router, $fresh);

            return response()->json([
                'status' => 'success',
                'data' => $connections,
                'total_active' => count($connections),
            ]);
        } catch (Exception $e) {
            $failures = $connService->recordFailure($router);

            return response()->json([
                'status' => 'error',
                'message' => 'Unable to query active PPP connections: '.$e->getMessage(),
                'data' => [],
                'total_active' => 0,
                'consecutive_failures' => $failures,
                'is_polling_paused' => $failures >= $connService::MAX_RETRIES,
            ], 502);
        }
    }

    /**
     * Disconnect/kick active PPP session.
     */
    public function kick(Request $request, Router $router, string $sessionId): JsonResponse
    {
        $bypassToken = $request->header('X-SafeMode-Bypass') ?? $request->input('bypass_token');

        try {
            $success = $this->pppService->kickActiveConnection($router, $sessionId, is_string($bypassToken) ? $bypassToken : null);

            return response()->json([
                'status' => $success ? 'success' : 'error',
                'message' => $success ? __('ppp.connection_disconnected') : __('ppp.disconnect_failed'),
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
