<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Hotspot;

use App\Application\SafeMode\Services\MikrotikSafeModeGuard;
use App\Domain\SafeMode\Enums\DestructiveAction;
use App\Http\Controllers\Controller;
use App\Infrastructure\Network\Mikrotik\Services\HotspotVoucherService;
use App\Infrastructure\Network\Mikrotik\Services\MikrotikConnectionService;
use App\Models\HotspotProfile;
use App\Models\Router;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HotspotProfileController extends Controller
{
    public function __construct(
        protected HotspotVoucherService $voucherService,
        protected MikrotikConnectionService $connectionService,
        protected MikrotikSafeModeGuard $safeModeGuard
    ) {}

    /**
     * List all profiles for router.
     */
    public function index(Router $router): JsonResponse
    {
        $profiles = HotspotProfile::where('router_id', $router->id)
            ->withCount('vouchers')
            ->orderBy('name')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $profiles,
        ]);
    }

    /**
     * Create profile and push to MikroTik.
     */
    public function store(Request $request, Router $router): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'shared_users' => ['nullable', 'integer', 'min:1', 'max:100'],
            'rate_limit' => ['nullable', 'string', 'max:50'], // e.g. 5M/10M
            'validity_duration' => ['nullable', 'string', 'max:20'], // e.g. 1
            'validity_unit' => ['nullable', 'in:minutes,hours,days'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'selling_price' => ['nullable', 'numeric', 'min:0'],
            'address_pool' => ['nullable', 'string', 'max:50'],
            'on_login_script' => ['nullable', 'string'],
            'on_logout_script' => ['nullable', 'string'],
        ]);

        $profile = HotspotProfile::create([
            'router_id' => $router->id,
            'name' => $validated['name'],
            'shared_users' => $validated['shared_users'] ?? 1,
            'rate_limit' => $validated['rate_limit'] ?? null,
            'validity_duration' => $validated['validity_duration'] ?? null,
            'validity_unit' => $validated['validity_unit'] ?? null,
            'price' => $validated['price'] ?? 0,
            'selling_price' => $validated['selling_price'] ?? 0,
            'address_pool' => $validated['address_pool'] ?? null,
            'on_login_script' => $validated['on_login_script'] ?? null,
            'on_logout_script' => $validated['on_logout_script'] ?? null,
            'is_active' => true,
        ]);

        // Push to MikroTik /ip/hotspot/user/profile/add
        try {
            $driver = $this->connectionService->getDriver($router);
            $params = [
                '=name='.$profile->name,
                '=shared-users='.(string) $profile->shared_users,
            ];
            if ($profile->rate_limit) {
                $params[] = '=rate-limit='.$profile->rate_limit;
            }
            if ($profile->address_pool) {
                $params[] = '=address-pool='.$profile->address_pool;
            }
            $driver->command('/ip/hotspot/user/profile/add', $params);
        } catch (\Throwable $e) {
            // Profile saved in DB even if MikroTik push fails
        }

        return response()->json([
            'status' => 'success',
            'message' => __('vouchers.hotspot_profile_created', ['name' => $profile->name]),
            'data' => $profile,
        ], 201);
    }

    /**
     * Pull profiles from MikroTik and sync to DB.
     */
    public function sync(Router $router): JsonResponse
    {
        try {
            $rosProfiles = $this->voucherService->getProfiles($router);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to query MikroTik profiles: '.$e->getMessage(),
            ], 502);
        }

        $synced = [];
        foreach ($rosProfiles as $p) {
            $name = $p['name'] ?? null;
            if (! $name) {
                continue;
            }

            $shared = isset($p['shared-users']) ? (int) $p['shared-users'] : 1;
            $rateLimit = $p['rate-limit'] ?? null;
            $pool = $p['address-pool'] ?? null;

            $record = HotspotProfile::updateOrCreate(
                [
                    'router_id' => $router->id,
                    'name' => $name,
                ],
                [
                    'shared_users' => $shared,
                    'rate_limit' => $rateLimit,
                    'address_pool' => $pool !== 'none' ? $pool : null,
                    'is_active' => true,
                ]
            );

            $synced[] = $record;
        }

        return response()->json([
            'status' => 'success',
            'message' => __('vouchers.hotspot_profiles_synced', ['count' => count($synced)]),
            'data' => $synced,
        ]);
    }

    /**
     * Update hotspot profile.
     */
    public function update(Request $request, Router $router, HotspotProfile $profile): JsonResponse
    {
        $validated = $request->validate([
            'shared_users' => ['nullable', 'integer', 'min:1', 'max:100'],
            'rate_limit' => ['nullable', 'string', 'max:50'],
            'validity_duration' => ['nullable', 'string', 'max:20'],
            'validity_unit' => ['nullable', 'in:minutes,hours,days'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'selling_price' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $profile->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => __('vouchers.hotspot_profile_updated', ['name' => $profile->name]),
            'data' => $profile,
        ], 200);
    }

    /**
     * Delete hotspot profile (Safe Mode protected).
     */
    public function destroy(Request $request, Router $router, HotspotProfile $profile): JsonResponse
    {
        $bypassToken = $request->header('X-SafeMode-Bypass') ?? $request->input('bypass_token');

        $this->safeModeGuard->enforce(
            router: $router,
            action: DestructiveAction::BulkDeleteProfiles,
            endpoint: $request->path(),
            payload: ['profile_id' => $profile->id, 'profile_name' => $profile->name],
            bypassToken: is_string($bypassToken) ? $bypassToken : null
        );

        $profile->delete();

        return response()->json([
            'status' => 'success',
            'message' => __('vouchers.hotspot_profile_deleted', ['name' => $profile->name]),
        ]);
    }
}
