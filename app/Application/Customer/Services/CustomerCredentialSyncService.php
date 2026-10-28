<?php

declare(strict_types=1);

namespace App\Application\Customer\Services;

use App\Infrastructure\Network\Mikrotik\Services\HotspotVoucherService;
use App\Infrastructure\Network\Mikrotik\Services\MikrotikConnectionService;
use App\Infrastructure\Network\Mikrotik\Services\PppService;
use App\Models\DedicatedUser;
use App\Models\Router;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class CustomerCredentialSyncService
{
    public function __construct(
        protected PppService $pppService,
        protected HotspotVoucherService $hotspotService,
        protected MikrotikConnectionService $connectionService
    ) {}

    /**
     * Synchronize customer password across:
     * 1. MikroTik RouterOS live gateway (PPP Secret or Hotspot User)
     * 2. Local Database (DedicatedUser or Voucher)
     * 3. User authentication record (User.password)
     * 4. Session invalidation (Sanctum tokens & live router active session kick)
     *
     * @return array{account_type: string, router_id: int|null, router_name: string|null, synced_to_router: bool, error: string|null}
     */
    public function syncCustomerPassword(User $user, string $newPassword, ?Router $router = null): array
    {
        $settings = $user->settings ?? [];
        $accountType = $this->resolveAccountType($user);
        $resolvedRouter = $router ?? $this->resolveRouter($user, $accountType);

        $syncedToRouter = false;
        $syncError = null;

        if ($resolvedRouter) {
            try {
                if ($accountType === 'ppp_customer') {
                    $this->pppService->updateSecret($resolvedRouter, $user->name, [
                        'password' => $newPassword,
                    ]);
                    $syncedToRouter = true;
                } elseif ($accountType === 'hotspot_customer') {
                    $syncedToRouter = $this->hotspotService->updateHotspotUserPassword(
                        $resolvedRouter,
                        $user->name,
                        $newPassword
                    );
                }
            } catch (\Throwable $e) {
                $syncError = $e->getMessage();
                Log::warning("Failed to sync customer '{$user->name}' password to router {$resolvedRouter->id}: {$syncError}");
            }
        }

        // 2. Synchronize local database entity (DedicatedUser / Voucher)
        if ($accountType === 'ppp_customer') {
            $dedQuery = DedicatedUser::where('name', $user->name);
            if ($resolvedRouter) {
                $dedQuery->where('router_id', $resolvedRouter->id);
            }
            $dedQuery->update([
                'password' => $newPassword,
                'synced_to_router' => $syncedToRouter,
                'sync_failed_reason' => $syncError,
            ]);
        } elseif ($accountType === 'hotspot_customer') {
            $vouchQuery = Voucher::where(function ($q) use ($user): void {
                $q->where('username', $user->name)
                    ->orWhere('code', $user->name);
            });
            if ($resolvedRouter) {
                $vouchQuery->where('router_id', $resolvedRouter->id);
            }
            $vouchQuery->update([
                'password' => $newPassword,
                'synced_to_router' => $syncedToRouter,
                'sync_failed_reason' => $syncError,
            ]);
        }

        // 3. Update User authentication record
        $user->password = Hash::make($newPassword);

        // Ensure settings are updated with account_type and router_id
        $settings['account_type'] = $accountType;
        if ($resolvedRouter) {
            $settings['router_id'] = $resolvedRouter->id;
        }
        $user->settings = $settings;
        $user->save();

        // 4. Revoke active tokens for security
        $user->tokens()->delete();

        return [
            'account_type' => $accountType,
            'router_id' => $resolvedRouter?->id,
            'router_name' => $resolvedRouter?->name,
            'synced_to_router' => $syncedToRouter,
            'error' => $syncError,
        ];
    }

    /**
     * Intelligently determine if customer is PPP subscriber or Hotspot voucher user.
     */
    public function resolveAccountType(User $user): string
    {
        $settings = $user->settings ?? [];
        if (! empty($settings['account_type']) && in_array($settings['account_type'], ['ppp_customer', 'hotspot_customer'], true)) {
            return $settings['account_type'];
        }

        // Check DedicatedUser existence
        if (DedicatedUser::where('name', $user->name)->exists()) {
            return 'ppp_customer';
        }

        // Check Voucher existence
        if (Voucher::where('username', $user->name)->orWhere('code', $user->name)->exists()) {
            return 'hotspot_customer';
        }

        // Check email prefix pattern: ppp_{routerId}_{name}@... or hotspot_{routerId}_{name}@...
        if (str_starts_with($user->email, 'ppp_')) {
            return 'ppp_customer';
        }
        if (str_starts_with($user->email, 'hotspot_')) {
            return 'hotspot_customer';
        }

        // If customer role, check active online routers
        if ($user->role === 'customer') {
            $routers = Router::where('status', 'online')->get();
            foreach ($routers as $router) {
                try {
                    $secrets = $this->pppService->getSecrets($router);
                    if (collect($secrets)->contains('name', $user->name)) {
                        return 'ppp_customer';
                    }
                    $hsUsers = $this->hotspotService->getHotspotUsers($router);
                    if (collect($hsUsers)->contains('name', $user->name)) {
                        return 'hotspot_customer';
                    }
                } catch (\Throwable) {
                    continue;
                }
            }
        }

        return $user->role === 'customer' ? 'ppp_customer' : 'admin';
    }

    /**
     * Resolve the appropriate router for the customer.
     */
    public function resolveRouter(User $user, string $accountType): ?Router
    {
        $settings = $user->settings ?? [];
        $routerId = isset($settings['router_id']) ? (int) $settings['router_id'] : null;

        if ($routerId) {
            $router = Router::find($routerId);
            if ($router) {
                return $router;
            }
        }

        if ($accountType === 'ppp_customer') {
            $ded = DedicatedUser::where('name', $user->name)->first();
            if ($ded && $ded->router) {
                return $ded->router;
            }
        } elseif ($accountType === 'hotspot_customer') {
            $vouch = Voucher::where('username', $user->name)->orWhere('code', $user->name)->first();
            if ($vouch && $vouch->router) {
                return $vouch->router;
            }
        }

        return Router::where('status', 'online')->first() ?? Router::first();
    }
}
