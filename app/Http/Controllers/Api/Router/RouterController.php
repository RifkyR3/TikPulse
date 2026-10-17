<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Router;

use App\Application\SafeMode\Services\MikrotikSafeModeGuard;
use App\Domain\SafeMode\Enums\DestructiveAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Router\StoreRouterRequest;
use App\Http\Requests\Router\UpdateRouterRequest;
use App\Http\Resources\RouterResource;
use App\Infrastructure\Network\Mikrotik\Services\MikrotikConnectionService;
use App\Models\Router;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RouterController extends Controller
{
    public function __construct(
        protected MikrotikConnectionService $connectionService,
        protected MikrotikSafeModeGuard $safeModeGuard
    ) {}

    /**
     * List all registered MikroTik routers.
     */
    public function index(): AnonymousResourceCollection
    {
        $routers = Router::orderBy('name')->get();

        return RouterResource::collection($routers);
    }

    /**
     * Store a new MikroTik router configuration.
     */
    public function store(StoreRouterRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $plainPassword = (string) $validated['password'];
        unset($validated['password']);

        $router = new Router($validated);
        $router->setPlainPassword($plainPassword);
        $router->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Router registered successfully.',
            'data' => [
                'router' => new RouterResource($router),
            ],
        ], 201);
    }

    /**
     * Show single router details.
     */
    public function show(Router $router): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => [
                'router' => new RouterResource($router),
            ],
        ]);
    }

    /**
     * Update an existing router profile.
     */
    public function update(UpdateRouterRequest $request, Router $router): JsonResponse
    {
        $validated = $request->validated();

        if (! empty($validated['password'])) {
            $router->setPlainPassword((string) $validated['password']);
            unset($validated['password']);
        }

        $router->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Router updated successfully.',
            'data' => [
                'router' => new RouterResource($router),
            ],
        ]);
    }

    /**
     * Delete router registration (Protected by Safe Mode guardrail).
     */
    public function destroy(Request $request, Router $router): JsonResponse
    {
        $bypassToken = $request->header('X-SafeMode-Bypass') ?? $request->input('bypass_token');

        $this->safeModeGuard->enforce(
            router: $router,
            action: DestructiveAction::RouterDeletion,
            endpoint: $request->path(),
            payload: ['router_id' => $router->id],
            bypassToken: is_string($bypassToken) ? $bypassToken : null
        );

        $router->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Router deleted successfully.',
        ]);
    }

    /**
     * Test socket / REST connection and sync system resources.
     */
    public function testConnection(Router $router): JsonResponse
    {
        $this->connectionService->resetFailures($router);
        $result = $this->connectionService->testAndSyncTelemetry($router, force: true);

        $freshRouter = $router->fresh();
        if (! empty($result['resource']) && is_array($result['resource'])) {
            $res = $result['resource'];
            if (isset($res['cpu_load'])) {
                $freshRouter->cpu_load = (int) $res['cpu_load'];
            }
            if (isset($res['free_memory'])) {
                $freshRouter->free_memory_bytes = (int) $res['free_memory'];
            }
            if (isset($res['total_memory'])) {
                $freshRouter->total_memory_bytes = (int) $res['total_memory'];
            }
            if (isset($res['uptime'])) {
                $freshRouter->uptime = (string) $res['uptime'];
            }
            if (isset($res['version'])) {
                $freshRouter->ros_version = (string) $res['version'];
            }
            if (isset($res['board_name'])) {
                $freshRouter->board_model = (string) $res['board_name'];
            }
        }

        return response()->json([
            'status' => $result['success'] ? 'success' : 'error',
            'message' => $result['success'] ? 'Connection to MikroTik verified.' : 'Connection test failed.',
            'data' => array_merge($result, [
                'router' => new RouterResource($freshRouter),
            ]),
        ], $result['success'] ? 200 : 502);
    }

    /**
     * Get live telemetry data for router.
     */
    public function telemetry(Router $router): JsonResponse
    {
        $result = $this->connectionService->testAndSyncTelemetry($router, force: false);

        if (! $result['success'] && ($result['is_circuit_open'] ?? false)) {
            return response()->json([
                'status' => 'offline',
                'code' => 'ROUTER_OFFLINE',
                'message' => $result['error'] ?? 'Router is offline. Polling paused to prevent timeouts.',
                'data' => [
                    'router' => new RouterResource($router->fresh()),
                    'telemetry' => [],
                    'consecutive_failures' => $result['consecutive_failures'] ?? 3,
                    'is_polling_paused' => true,
                ],
            ], 503);
        }

        $freshRouter = $router->fresh();
        if (! empty($result['resource']) && is_array($result['resource'])) {
            $res = $result['resource'];
            if (isset($res['cpu_load'])) {
                $freshRouter->cpu_load = (int) $res['cpu_load'];
            }
            if (isset($res['free_memory'])) {
                $freshRouter->free_memory_bytes = (int) $res['free_memory'];
            }
            if (isset($res['total_memory'])) {
                $freshRouter->total_memory_bytes = (int) $res['total_memory'];
            }
            if (isset($res['uptime'])) {
                $freshRouter->uptime = (string) $res['uptime'];
            }
            if (isset($res['version'])) {
                $freshRouter->ros_version = (string) $res['version'];
            }
            if (isset($res['board_name'])) {
                $freshRouter->board_model = (string) $res['board_name'];
            }
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'router' => new RouterResource($freshRouter),
                'telemetry' => $result['resource'] ?? [],
                'consecutive_failures' => $result['consecutive_failures'] ?? 0,
                'is_polling_paused' => false,
            ],
        ]);
    }

    /**
     * Reboot router (High-risk command, strictly protected by Safe Mode).
     */
    public function reboot(Request $request, Router $router): JsonResponse
    {
        $bypassToken = $request->header('X-SafeMode-Bypass') ?? $request->input('bypass_token');
        $bypassReason = $request->header('X-Bypass-Reason') ?? $request->input('bypass_reason');

        $this->safeModeGuard->enforce(
            router: $router,
            action: DestructiveAction::SystemReboot,
            endpoint: $request->path(),
            payload: ['action' => 'reboot'],
            bypassToken: is_string($bypassToken) ? $bypassToken : null,
            bypassReason: is_string($bypassReason) ? $bypassReason : null
        );

        $driver = $this->connectionService->getDriver($router);
        $driver->command('/system/reboot');

        return response()->json([
            'status' => 'success',
            'message' => 'Reboot command sent to MikroTik router.',
        ]);
    }
}
