<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Hotspot;

use App\Application\SafeMode\Services\MikrotikSafeModeGuard;
use App\Http\Controllers\Controller;
use App\Infrastructure\Network\Mikrotik\Services\HotspotVoucherService;
use App\Models\Router;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ActiveUserController extends Controller
{
    public function __construct(
        protected HotspotVoucherService $hotspotService,
        protected MikrotikSafeModeGuard $safeModeGuard
    ) {}

    /**
     * List active hotspot sessions for the specified router.
     */
    public function index(Router $router): JsonResponse
    {
        $connService = $this->hotspotService->getConnectionService();

        // Fast-fail if router has 3+ consecutive connection failures
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

        $cacheKey = "router_active_sessions_{$router->id}";
        if (Cache::has($cacheKey)) {
            $cached = Cache::get($cacheKey);
            if (is_array($cached)) {
                return response()->json([
                    'status' => 'success',
                    'data' => $cached,
                    'total_active' => count($cached),
                    'from_cache' => true,
                ]);
            }
        }

        try {
            $sessions = $this->hotspotService->getActiveUsers($router);

            $formatBytes = function (int $bytes): string {
                if ($bytes <= 0) {
                    return '0 B';
                }
                $k = 1024;
                $sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
                $i = (int) floor(log($bytes) / log($k));

                return round($bytes / pow($k, $i), 2).' '.($sizes[$i] ?? 'B');
            };

            $formatted = array_map(function (array $item) use ($formatBytes): array {
                $bIn = isset($item['bytes-in']) ? (int) $item['bytes-in'] : 0;
                $bOut = isset($item['bytes-out']) ? (int) $item['bytes-out'] : 0;

                return [
                    'id' => $item['.id'] ?? '',
                    'user' => $item['user'] ?? 'Unknown',
                    'address' => $item['address'] ?? '-',
                    'mac_address' => $item['mac-address'] ?? '-',
                    'uptime' => $item['uptime'] ?? '0s',
                    'session_time_left' => $item['session-time-left'] ?? null,
                    'bytes_in' => $bIn,
                    'bytes_out' => $bOut,
                    'bytes_in_formatted' => $formatBytes($bIn),
                    'bytes_out_formatted' => $formatBytes($bOut),
                    'login_by' => $item['login-by'] ?? 'http-pap',
                ];
            }, $sessions);

            Cache::put($cacheKey, $formatted, 3);

            return response()->json([
                'status' => 'success',
                'data' => $formatted,
                'total_active' => count($formatted),
            ]);
        } catch (Exception $e) {
            $failures = $connService->recordFailure($router);
            Cache::forget($cacheKey);

            return response()->json([
                'status' => 'error',
                'message' => 'Unable to query active hotspot users: '.$e->getMessage(),
                'data' => [],
                'total_active' => 0,
                'consecutive_failures' => $failures,
                'is_polling_paused' => $failures >= $connService::MAX_RETRIES,
            ], 502);
        }
    }

    /**
     * Kick active hotspot session (Checked against Safe Mode guard).
     */
    public function kick(Request $request, Router $router, string $sessionId): JsonResponse
    {
        $bypassToken = $request->header('X-SafeMode-Bypass') ?? $request->input('bypass_token');

        try {
            $success = $this->hotspotService->kickActiveUser($router, $sessionId, is_string($bypassToken) ? $bypassToken : null);

            // Invalidate cache immediately on session kick
            Cache::forget("router_active_sessions_{$router->id}");

            return response()->json([
                'status' => $success ? 'success' : 'error',
                'message' => $success ? 'Hotspot user session disconnected.' : 'Failed to disconnect user session.',
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Live traffic stream for interface (e.g. ether1).
     */
    public function traffic(Request $request, Router $router): JsonResponse
    {
        $interface = (string) $request->query('interface', 'ether1');
        $connService = $this->hotspotService->getConnectionService();

        // Fast-fail if router has 3+ consecutive connection failures
        if ($connService->isCircuitOpen($router)) {
            $failures = $connService->getConsecutiveFailures($router);

            return response()->json([
                'status' => 'offline',
                'code' => 'ROUTER_OFFLINE',
                'message' => "Router is offline. Polling paused after {$failures} consecutive failed attempts.",
                'data' => [
                    'interface' => $interface,
                    'rx_bps' => 0,
                    'tx_bps' => 0,
                ],
                'is_polling_paused' => true,
                'consecutive_failures' => $failures,
            ], 503);
        }

        $cacheKey = "router_traffic_{$router->id}_{$interface}";
        if (Cache::has($cacheKey)) {
            $cached = Cache::get($cacheKey);
            if (is_array($cached)) {
                return response()->json([
                    'status' => 'success',
                    'data' => $cached,
                    'from_cache' => true,
                ]);
            }
        }

        try {
            $traffic = $this->hotspotService->getInterfaceTraffic($router, $interface);
            Cache::put($cacheKey, $traffic, 2);

            return response()->json([
                'status' => 'success',
                'data' => $traffic,
            ]);
        } catch (Exception $e) {
            $failures = $connService->recordFailure($router);
            Cache::forget($cacheKey);

            return response()->json([
                'status' => 'error',
                'message' => 'Unable to query interface traffic: '.$e->getMessage(),
                'data' => [
                    'interface' => $interface,
                    'rx_bps' => 0,
                    'tx_bps' => 0,
                ],
                'consecutive_failures' => $failures,
                'is_polling_paused' => $failures >= $connService::MAX_RETRIES,
            ], 502);
        }
    }

    /**
     * Retrieve list of interfaces for the specified router with device-specific fallback.
     */
    public function interfaces(Router $router): JsonResponse
    {
        $connService = $this->hotspotService->getConnectionService();

        if ($connService->isCircuitOpen($router)) {
            $defaults = $this->hotspotService->getDefaultInterfacesForRouter($router);
            $formatted = array_map(fn (array $item) => [
                'name' => $item['name'],
                'type' => $item['type'] ?? 'ether',
                'comment' => $item['comment'] ?? null,
                'running' => false,
                'disabled' => false,
                'label' => isset($item['comment']) ? "{$item['name']} ({$item['comment']})" : $item['name'],
            ], $defaults);

            return response()->json([
                'status' => 'success',
                'data' => $formatted,
                'is_offline' => true,
            ]);
        }

        try {
            $interfaces = $this->hotspotService->getInterfaces($router);

            return response()->json([
                'status' => 'success',
                'data' => $interfaces,
            ]);
        } catch (Exception) {
            $defaults = $this->hotspotService->getDefaultInterfacesForRouter($router);
            $formatted = array_map(fn (array $item) => [
                'name' => $item['name'],
                'type' => $item['type'] ?? 'ether',
                'comment' => $item['comment'] ?? null,
                'running' => false,
                'disabled' => false,
                'label' => isset($item['comment']) ? "{$item['name']} ({$item['comment']})" : $item['name'],
            ], $defaults);

            return response()->json([
                'status' => 'success',
                'data' => $formatted,
                'fallback' => true,
            ]);
        }
    }
}
