<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Ppp;

use App\Application\SafeMode\Services\MikrotikSafeModeGuard;
use App\Domain\SafeMode\Enums\DestructiveAction;
use App\Http\Controllers\Controller;
use App\Infrastructure\Network\Mikrotik\Services\PppService;
use App\Models\PppProfile;
use App\Models\Router;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PppProfileController extends Controller
{
    public function __construct(
        protected PppService $pppService,
        protected MikrotikSafeModeGuard $safeModeGuard
    ) {}

    /**
     * List all PPP profiles for the router.
     */
    public function index(Router $router): JsonResponse
    {
        $profiles = PppProfile::where('router_id', $router->id)
            ->withCount('dedicatedUsers')
            ->orderBy('name')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $profiles,
        ]);
    }

    /**
     * Create a new PPP profile in DB and push to MikroTik.
     */
    public function store(Request $request, Router $router): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'local_address' => ['nullable', 'string', 'max:50'],
            'remote_address' => ['nullable', 'string', 'max:100'],
            'rate_limit' => ['nullable', 'string', 'max:50'], // e.g. 10M/20M
            'only_one' => ['nullable', 'boolean'],
            'dns_server' => ['nullable', 'string', 'max:100'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'comment' => ['nullable', 'string', 'max:255'],
        ]);

        $profile = PppProfile::create([
            'router_id' => $router->id,
            'name' => $validated['name'],
            'local_address' => $validated['local_address'] ?? null,
            'remote_address' => $validated['remote_address'] ?? null,
            'rate_limit' => $validated['rate_limit'] ?? null,
            'only_one' => $validated['only_one'] ?? true,
            'dns_server' => $validated['dns_server'] ?? null,
            'price' => $validated['price'] ?? 0,
            'comment' => $validated['comment'] ?? null,
            'is_active' => true,
        ]);

        try {
            $this->pppService->addProfile($router, [
                'name' => $profile->name,
                'local_address' => $profile->local_address,
                'remote_address' => $profile->remote_address,
                'rate_limit' => $profile->rate_limit,
                'only_one' => $profile->only_one,
                'dns_server' => $profile->dns_server,
                'comment' => $profile->comment,
            ]);
        } catch (\Throwable) {
            // Profile is still saved in DB even if MikroTik push fails
        }

        return response()->json([
            'status' => 'success',
            'message' => __('ppp.profile_created', ['name' => $profile->name]),
            'data' => $profile,
        ], 201);
    }

    /**
     * Pull PPP profiles from MikroTik and synchronize to DB.
     */
    public function sync(Router $router): JsonResponse
    {
        try {
            $rosProfiles = $this->pppService->getProfiles($router, true);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to query MikroTik PPP profiles: '.$e->getMessage(),
            ], 502);
        }

        $synced = [];
        foreach ($rosProfiles as $p) {
            $name = $p['name'] ?? null;
            if (! $name) {
                continue;
            }

            $record = PppProfile::updateOrCreate(
                [
                    'router_id' => $router->id,
                    'name' => $name,
                ],
                [
                    'local_address' => $p['local_address'] ?? null,
                    'remote_address' => $p['remote_address'] ?? null,
                    'rate_limit' => $p['rate_limit'] ?? null,
                    'only_one' => $p['only_one'] ?? true,
                    'dns_server' => $p['dns_server'] ?? null,
                    'comment' => $p['comment'] ?? null,
                    'is_active' => true,
                ]
            );

            $synced[] = $record;
        }

        return response()->json([
            'status' => 'success',
            'message' => __('ppp.profiles_synced', ['count' => count($synced)]),
            'data' => $synced,
        ]);
    }

    /**
     * Update an existing PPP profile.
     */
    public function update(Request $request, Router $router, PppProfile $profile): JsonResponse
    {
        $validated = $request->validate([
            'local_address' => ['nullable', 'string', 'max:50'],
            'remote_address' => ['nullable', 'string', 'max:100'],
            'rate_limit' => ['nullable', 'string', 'max:50'],
            'only_one' => ['nullable', 'boolean'],
            'dns_server' => ['nullable', 'string', 'max:100'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'comment' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $profile->update($validated);

        try {
            $this->pppService->updateProfile($router, $profile->name, [
                'local_address' => $profile->local_address,
                'remote_address' => $profile->remote_address,
                'rate_limit' => $profile->rate_limit,
                'only_one' => $profile->only_one,
                'dns_server' => $profile->dns_server,
                'comment' => $profile->comment,
            ]);
        } catch (\Throwable) {
            // Updated in DB even if MikroTik push fails
        }

        return response()->json([
            'status' => 'success',
            'message' => __('ppp.profile_updated', ['name' => $profile->name]),
            'data' => $profile,
        ]);
    }

    /**
     * Delete PPP profile (Safe Mode protected).
     */
    public function destroy(Request $request, Router $router, PppProfile $profile): JsonResponse
    {
        $bypassToken = $request->header('X-SafeMode-Bypass') ?? $request->input('bypass_token');

        $this->safeModeGuard->enforce(
            router: $router,
            action: DestructiveAction::BulkDeletePppProfiles,
            endpoint: $request->path(),
            payload: ['profile_id' => $profile->id, 'profile_name' => $profile->name],
            bypassToken: is_string($bypassToken) ? $bypassToken : null
        );

        try {
            $this->pppService->removeProfile($router, $profile->name, is_string($bypassToken) ? $bypassToken : null, false);
        } catch (\Throwable) {
        }

        $profile->delete();

        return response()->json([
            'status' => 'success',
            'message' => __('ppp.profile_deleted', ['name' => $profile->name]),
        ]);
    }

    /**
     * Get IP Pools from the router for profile assignment.
     */
    public function pools(Router $router): JsonResponse
    {
        $pools = $this->pppService->getIpPools($router);

        return response()->json([
            'status' => 'success',
            'data' => $pools,
        ]);
    }
}
