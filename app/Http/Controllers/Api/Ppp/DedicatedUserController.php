<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Ppp;

use App\Application\SafeMode\Services\MikrotikSafeModeGuard;
use App\Domain\SafeMode\Enums\DestructiveAction;
use App\Http\Controllers\Controller;
use App\Infrastructure\Network\Mikrotik\Services\PppService;
use App\Models\DedicatedUser;
use App\Models\PppProfile;
use App\Models\Router;
use App\Models\User;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class DedicatedUserController extends Controller
{
    public function __construct(
        protected PppService $pppService,
        protected MikrotikSafeModeGuard $safeModeGuard
    ) {}

    /**
     * List all dedicated users with live connection status.
     */
    public function index(Request $request, Router $router): JsonResponse
    {
        $query = DedicatedUser::where('router_id', $router->id)
            ->with('pppProfile');

        // Search filter
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%")
                    ->orWhere('customer_phone', 'like', "%{$search}%")
                    ->orWhere('remote_address', 'like', "%{$search}%")
                    ->orWhere('comment', 'like', "%{$search}%");
            });
        }

        // Profile filter
        if ($profile = $request->input('profile')) {
            $query->where('profile', $profile);
        }

        // Status filter (active vs disabled)
        $status = $request->input('status');
        if ($status === 'active') {
            $query->where('disabled', false);
        } elseif ($status === 'disabled') {
            $query->where('disabled', true);
        }

        $users = $query->orderBy('created_at', 'desc')->get();

        // Fetch live active connections to overlay online status
        $activeMap = [];
        try {
            $fresh = $request->boolean('fresh', false);
            $activeList = $this->pppService->getActiveConnections($router, $fresh);
            foreach ($activeList as $act) {
                if (isset($act['name'])) {
                    $activeMap[$act['name']] = $act;
                }
            }
        } catch (\Throwable) {
            // Non-fatal if router connection fails
        }

        $onlineCount = 0;
        $disabledCount = 0;
        $totalMonthlyRevenue = 0.0;

        $transformed = $users->map(function (DedicatedUser $user) use ($activeMap, &$onlineCount, &$disabledCount, &$totalMonthlyRevenue): array {
            $active = $activeMap[$user->name] ?? null;
            $isOnline = $active !== null;

            if ($isOnline) {
                $onlineCount++;
            }
            if ($user->disabled) {
                $disabledCount++;
            }
            $totalMonthlyRevenue += (float) $user->monthly_price;

            return [
                'id' => $user->id,
                'router_id' => $user->router_id,
                'ppp_profile_id' => $user->ppp_profile_id,
                'name' => $user->name,
                'password' => $user->password,
                'service' => $user->service,
                'profile' => $user->profile,
                'local_address' => $user->local_address,
                'remote_address' => $user->remote_address,
                'caller_id' => $user->caller_id,
                'routes' => $user->routes,
                'customer_name' => $user->customer_name,
                'customer_phone' => $user->customer_phone,
                'customer_address' => $user->customer_address,
                'monthly_price' => (float) $user->monthly_price,
                'billing_cycle' => $user->billing_cycle,
                'comment' => $user->comment,
                'disabled' => $user->disabled,
                'synced_to_router' => $user->synced_to_router,
                'sync_failed_reason' => $user->sync_failed_reason,
                'created_at' => $user->created_at?->toIso8601String(),
                'updated_at' => $user->updated_at?->toIso8601String(),
                'is_online' => $isOnline,
                'active_session' => $active,
                'profile_detail' => $user->pppProfile ? [
                    'id' => $user->pppProfile->id,
                    'name' => $user->pppProfile->name,
                    'rate_limit' => $user->pppProfile->rate_limit,
                ] : null,
            ];
        });

        // If filter was 'online', filter transformed list
        if ($status === 'online') {
            $transformed = $transformed->filter(fn (array $item) => $item['is_online'])->values();
        }

        return response()->json([
            'status' => 'success',
            'data' => $transformed,
            'meta' => [
                'total' => $users->count(),
                'online' => $onlineCount,
                'disabled' => $disabledCount,
                'total_monthly_revenue' => $totalMonthlyRevenue,
            ],
        ]);
    }

    /**
     * Store a new dedicated user in DB and push secret to MikroTik.
     */
    public function store(Request $request, Router $router): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'password' => ['required', 'string', 'max:100'],
            'service' => ['nullable', 'string', 'in:pppoe,any,l2tp,sstp,pptp,ovpn'],
            'profile' => ['nullable', 'string', 'max:100'],
            'local_address' => ['nullable', 'string', 'max:50'],
            'remote_address' => ['nullable', 'string', 'max:50'],
            'caller_id' => ['nullable', 'string', 'max:50'],
            'routes' => ['nullable', 'string', 'max:255'],
            'customer_name' => ['nullable', 'string', 'max:150'],
            'customer_phone' => ['nullable', 'string', 'max:50'],
            'customer_address' => ['nullable', 'string'],
            'monthly_price' => ['nullable', 'numeric', 'min:0'],
            'billing_cycle' => ['nullable', 'string', 'max:30'],
            'comment' => ['nullable', 'string', 'max:255'],
            'disabled' => ['nullable', 'boolean'],
        ]);

        // Check uniqueness per router
        if (DedicatedUser::where('router_id', $router->id)->where('name', $validated['name'])->exists()) {
            return response()->json([
                'status' => 'error',
                'message' => __('ppp.user_exists', ['name' => $validated['name']]),
            ], 422);
        }

        $profileName = $validated['profile'] ?? 'default';
        $pppProfile = PppProfile::where('router_id', $router->id)->where('name', $profileName)->first();

        // If monthly_price is empty, null, or zero, auto-inherit from profile price
        $monthlyPrice = isset($validated['monthly_price']) && $validated['monthly_price'] !== '' && $validated['monthly_price'] !== null && (float) $validated['monthly_price'] > 0
            ? (float) $validated['monthly_price']
            : (float) ($pppProfile?->price ?? 0.0);

        $user = new DedicatedUser([
            'router_id' => $router->id,
            'ppp_profile_id' => $pppProfile?->id,
            'name' => $validated['name'],
            'password' => $validated['password'],
            'service' => $validated['service'] ?? 'pppoe',
            'profile' => $profileName,
            'local_address' => $validated['local_address'] ?? null,
            'remote_address' => $validated['remote_address'] ?? null,
            'caller_id' => $validated['caller_id'] ?? null,
            'routes' => $validated['routes'] ?? null,
            'customer_name' => $validated['customer_name'] ?? null,
            'customer_phone' => $validated['customer_phone'] ?? null,
            'customer_address' => $validated['customer_address'] ?? null,
            'monthly_price' => $monthlyPrice,
            'billing_cycle' => $validated['billing_cycle'] ?? 'monthly',
            'comment' => $validated['comment'] ?? null,
            'disabled' => $validated['disabled'] ?? false,
            'synced_to_router' => false,
        ]);

        try {
            $this->pppService->addSecret($router, [
                'name' => $user->name,
                'password' => $user->password,
                'service' => $user->service,
                'profile' => $user->profile,
                'local_address' => $user->local_address,
                'remote_address' => $user->remote_address,
                'caller_id' => $user->caller_id,
                'routes' => $user->routes,
                'comment' => $user->comment,
                'disabled' => $user->disabled,
            ]);
            $user->synced_to_router = true;
        } catch (\Throwable $e) {
            $user->sync_failed_reason = $e->getMessage();
        }

        $user->save();

        return response()->json([
            'status' => 'success',
            'message' => __('ppp.user_created', ['name' => $user->name]),
            'data' => $user,
        ], 201);
    }

    /**
     * Update an existing dedicated user.
     */
    public function update(Request $request, Router $router, DedicatedUser $dedicatedUser): JsonResponse
    {
        $validated = $request->validate([
            'password' => ['nullable', 'string', 'max:100'],
            'service' => ['nullable', 'string', 'in:pppoe,any,l2tp,sstp,pptp,ovpn'],
            'profile' => ['nullable', 'string', 'max:100'],
            'local_address' => ['nullable', 'string', 'max:50'],
            'remote_address' => ['nullable', 'string', 'max:50'],
            'caller_id' => ['nullable', 'string', 'max:50'],
            'routes' => ['nullable', 'string', 'max:255'],
            'customer_name' => ['nullable', 'string', 'max:150'],
            'customer_phone' => ['nullable', 'string', 'max:50'],
            'customer_address' => ['nullable', 'string'],
            'monthly_price' => ['nullable', 'numeric', 'min:0'],
            'billing_cycle' => ['nullable', 'string', 'max:30'],
            'comment' => ['nullable', 'string', 'max:255'],
            'disabled' => ['nullable', 'boolean'],
        ]);

        $targetProfile = $validated['profile'] ?? $dedicatedUser->profile ?? 'default';
        $pppProfile = PppProfile::where('router_id', $router->id)->where('name', $targetProfile)->first();

        if (isset($validated['profile'])) {
            $dedicatedUser->ppp_profile_id = $pppProfile?->id;
        }

        // If monthly_price is empty, null, or zero, auto-inherit from profile price
        if (array_key_exists('monthly_price', $validated)) {
            if ($validated['monthly_price'] === null || $validated['monthly_price'] === '' || (float) $validated['monthly_price'] <= 0) {
                $validated['monthly_price'] = (float) ($pppProfile?->price ?? 0.0);
            } else {
                $validated['monthly_price'] = (float) $validated['monthly_price'];
            }
        }

        $dedicatedUser->fill($validated);

        try {
            $this->pppService->updateSecret($router, $dedicatedUser->name, [
                'password' => $dedicatedUser->password,
                'service' => $dedicatedUser->service,
                'profile' => $dedicatedUser->profile,
                'local_address' => $dedicatedUser->local_address,
                'remote_address' => $dedicatedUser->remote_address,
                'caller_id' => $dedicatedUser->caller_id,
                'routes' => $dedicatedUser->routes,
                'comment' => $dedicatedUser->comment,
                'disabled' => $dedicatedUser->disabled,
            ]);
            $dedicatedUser->synced_to_router = true;
            $dedicatedUser->sync_failed_reason = null;
        } catch (\Throwable $e) {
            $dedicatedUser->sync_failed_reason = $e->getMessage();
        }

        $dedicatedUser->save();

        if (array_key_exists('password', $validated) && ! empty($validated['password'])) {
            $customerUser = User::where('name', $dedicatedUser->name)
                ->orWhere('email', "ppp_{$router->id}_{$dedicatedUser->name}@customer.tikpulse.local")
                ->first();
            if ($customerUser) {
                $customerUser->password = Hash::make($dedicatedUser->password);
                $customerUser->tokens()->delete();
                $customerUser->save();
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => __('ppp.user_updated', ['name' => $dedicatedUser->name]),
            'data' => $dedicatedUser,
        ]);
    }

    /**
     * Toggle disabled status of dedicated user (quick disconnect if disabling).
     */
    public function toggle(Request $request, Router $router, DedicatedUser $dedicatedUser): JsonResponse
    {
        $newDisabled = ! $dedicatedUser->disabled;
        $dedicatedUser->disabled = $newDisabled;

        try {
            $this->pppService->toggleSecret($router, $dedicatedUser->name, $newDisabled);
            $dedicatedUser->synced_to_router = true;
        } catch (\Throwable $e) {
            $dedicatedUser->sync_failed_reason = $e->getMessage();
        }

        $dedicatedUser->save();

        return response()->json([
            'status' => 'success',
            'message' => __('ppp.user_status_changed', ['name' => $dedicatedUser->name, 'status' => $newDisabled ? 'disabled' : 'enabled']),
            'data' => $dedicatedUser,
        ]);
    }

    /**
     * Delete dedicated user (Safe Mode protected).
     */
    public function destroy(Request $request, Router $router, DedicatedUser $dedicatedUser): JsonResponse
    {
        $bypassToken = $request->header('X-SafeMode-Bypass') ?? $request->input('bypass_token');

        $this->safeModeGuard->enforce(
            router: $router,
            action: DestructiveAction::BulkDeletePppSecrets,
            endpoint: $request->path(),
            payload: ['username' => $dedicatedUser->name, 'id' => $dedicatedUser->id],
            bypassToken: is_string($bypassToken) ? $bypassToken : null
        );

        try {
            $this->pppService->removeSecret($router, $dedicatedUser->name, is_string($bypassToken) ? $bypassToken : null, false);
        } catch (\Throwable) {
        }

        $dedicatedUser->delete();

        return response()->json([
            'status' => 'success',
            'message' => __('ppp.user_deleted', ['name' => $dedicatedUser->name]),
        ]);
    }

    /**
     * Pull existing PPP secrets from MikroTik into database.
     */
    public function sync(Router $router): JsonResponse
    {
        try {
            $rosSecrets = $this->pppService->getSecrets($router, true);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to query MikroTik PPP secrets: '.$e->getMessage(),
            ], 502);
        }

        $synced = [];
        foreach ($rosSecrets as $secret) {
            $name = $secret['name'] ?? null;
            if (! $name) {
                continue;
            }

            $profileName = $secret['profile'] ?? 'default';
            $pppProfile = PppProfile::where('router_id', $router->id)->where('name', $profileName)->first();

            $record = DedicatedUser::firstOrNew([
                'router_id' => $router->id,
                'name' => $name,
            ]);

            $password = ! empty($secret['password']) ? (string) $secret['password'] : ($record->password ?: '123456');
            $record->password = $password;

            // Only update credentials and router settings, preserving custom customer fields if already existing
            if (! $record->exists) {
                $record->customer_name = $secret['comment'] ?? $name;
                $record->monthly_price = (float) ($pppProfile?->price ?? 0.0);
            } elseif ($record->monthly_price === null || (float) $record->monthly_price <= 0.0) {
                if (($pppProfile?->price ?? 0.0) > 0) {
                    $record->monthly_price = (float) $pppProfile->price;
                }
            }

            $record->ppp_profile_id = $pppProfile?->id;
            $record->service = $secret['service'] ?? 'pppoe';
            $record->profile = $profileName;
            $record->local_address = $secret['local_address'] ?? null;
            $record->remote_address = $secret['remote_address'] ?? null;
            $record->caller_id = $secret['caller_id'] ?? null;
            $record->routes = $secret['routes'] ?? null;
            $record->comment = $secret['comment'] ?? $record->comment;
            $record->disabled = (bool) ($secret['disabled'] ?? false);
            $record->synced_to_router = true;
            $record->sync_failed_reason = null;

            $record->save();

            // Synchronize customer login account into users table
            User::updateOrCreate(
                [
                    'email' => "ppp_{$router->id}_{$name}@customer.tikpulse.local",
                ],
                [
                    'name' => $name,
                    'role' => 'customer',
                    'password' => Hash::make($password),
                    'is_active' => ! $record->disabled,
                    'settings' => [
                        'account_type' => 'ppp_customer',
                        'router_id' => $router->id,
                        'dedicated_user_id' => $record->id,
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
            'message' => __('ppp.users_synced', ['count' => count($synced)]),
            'data' => $synced,
        ]);
    }
}
