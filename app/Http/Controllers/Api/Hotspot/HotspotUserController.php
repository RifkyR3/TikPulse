<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Hotspot;

use App\Application\SafeMode\Services\MikrotikSafeModeGuard;
use App\Domain\SafeMode\Enums\DestructiveAction;
use App\Http\Controllers\Controller;
use App\Infrastructure\Network\Mikrotik\Services\HotspotVoucherService;
use App\Models\HotspotProfile;
use App\Models\Router;
use App\Models\User;
use App\Models\Voucher;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

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
                'message' => __('common.router_offline', ['name' => $router->name]),
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
                'message' => __('vouchers.hotspot_user_created', ['name' => $validated['name']]),
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
                    'message' => __('vouchers.hotspot_user_not_found', ['name' => $username]),
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'message' => __('vouchers.hotspot_user_deleted', ['name' => $username]),
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to remove hotspot user: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Pull existing Hotspot Users from MikroTik into database (vouchers and customer user accounts).
     */
    public function sync(Router $router): JsonResponse
    {
        try {
            $rosUsers = $this->hotspotService->getHotspotUsers($router, true);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to query MikroTik Hotspot users: '.$e->getMessage(),
            ], 502);
        }

        $defaultProfile = HotspotProfile::where('router_id', $router->id)->first();
        if (! $defaultProfile) {
            $defaultProfile = HotspotProfile::create([
                'router_id' => $router->id,
                'name' => 'default',
                'shared_users' => 1,
                'is_active' => true,
            ]);
        }

        $synced = [];
        foreach ($rosUsers as $item) {
            $name = $item['name'] ?? null;
            if (! $name) {
                continue;
            }

            $profileName = $item['profile'] ?? 'default';
            $profile = HotspotProfile::where('router_id', $router->id)->where('name', $profileName)->first() ?? $defaultProfile;

            $record = Voucher::firstOrNew([
                'router_id' => $router->id,
                'username' => $name,
            ]);

            $password = ! empty($item['password']) ? (string) $item['password'] : ($record->password ?: $name);

            $record->password = $password;
            $record->profile_id = $profile->id;
            $record->comment = $item['comment'] ?? $record->comment;
            $record->time_limit = $item['limit_uptime'] ?? $record->time_limit;
            $record->data_limit_bytes = isset($item['limit_bytes_total']) ? (int) $item['limit_bytes_total'] : $record->data_limit_bytes;
            $record->synced_to_router = true;
            $record->sync_failed_reason = null;
            $record->status = ! empty($item['disabled']) ? 'disabled' : 'available';

            $record->save();

            // Synchronize customer login account into users table
            User::updateOrCreate(
                [
                    'email' => "hotspot_{$router->id}_{$name}@customer.tikpulse.local",
                ],
                [
                    'name' => $name,
                    'role' => 'customer',
                    'password' => Hash::make($password),
                    'is_active' => empty($item['disabled']),
                    'settings' => [
                        'account_type' => 'hotspot_customer',
                        'router_id' => $router->id,
                        'voucher_id' => $record->id,
                        'theme' => 'system',
                        'accent_color' => 'sky',
                        'preferred_locale' => 'id',
                    ],
                ]
            );

            $synced[] = $record;
        }

        return response()->json([
            'status' => 'success',
            'message' => __('vouchers.hotspot_users_synced', ['count' => count($synced)]),
            'data' => $synced,
        ]);
    }
}
