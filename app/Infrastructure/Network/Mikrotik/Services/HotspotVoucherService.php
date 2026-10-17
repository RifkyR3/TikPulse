<?php

declare(strict_types=1);

namespace App\Infrastructure\Network\Mikrotik\Services;

use App\Application\SafeMode\Services\MikrotikSafeModeGuard;
use App\Domain\SafeMode\Enums\DestructiveAction;
use App\Models\Router;
use Illuminate\Support\Facades\Cache;

class HotspotVoucherService
{
    public function __construct(
        protected MikrotikConnectionService $connectionService,
        protected MikrotikSafeModeGuard $safeModeGuard
    ) {}

    public function getConnectionService(): MikrotikConnectionService
    {
        return $this->connectionService;
    }

    /**
     * Get active hotspot sessions from router (transiently cached for 3 seconds).
     *
     * @return array<int, array<string, string>>
     */
    public function getActiveUsers(Router $router, bool $fresh = false): array
    {
        $cacheKey = "ros_active_users_{$router->id}";
        if (! $fresh && Cache::has($cacheKey)) {
            $cached = Cache::get($cacheKey);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $driver = $this->connectionService->getDriver($router);
        $data = $driver->command('/ip/hotspot/active/print');

        Cache::put($cacheKey, $data, 3);

        return $data;
    }

    /**
     * Get hotspot user profiles directly from router (cached for 15 seconds).
     *
     * @return array<int, array<string, string>>
     */
    public function getProfiles(Router $router, bool $fresh = false): array
    {
        $cacheKey = "ros_profiles_{$router->id}";
        if (! $fresh && Cache::has($cacheKey)) {
            $cached = Cache::get($cacheKey);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $driver = $this->connectionService->getDriver($router);
        $data = $driver->command('/ip/hotspot/user/profile/print');

        Cache::put($cacheKey, $data, 15);

        return $data;
    }

    /**
     * Add single hotspot user / voucher on router.
     *
     * @param  array{name: string, password: string, profile: string, time_limit?: string, data_limit?: string, comment?: string}  $data
     * @return array<int, array<string, string>>
     */
    public function addHotspotUser(Router $router, array $data): array
    {
        $driver = $this->connectionService->getDriver($router);

        $params = [
            '=name='.$data['name'],
            '=password='.$data['password'],
            '=profile='.$data['profile'],
        ];

        if (! empty($data['time_limit'])) {
            $params[] = '=limit-uptime='.$data['time_limit'];
        }

        if (! empty($data['data_limit'])) {
            $params[] = '=limit-bytes-total='.(string) $data['data_limit'];
        }

        if (! empty($data['comment'])) {
            $params[] = '=comment='.$data['comment'];
        }

        return $driver->command('/ip/hotspot/user/add', $params);
    }

    /**
     * Remove single hotspot user from router (subject to Safe Mode guard).
     */
    public function removeHotspotUser(Router $router, string $username, ?string $bypassToken = null, bool $enforceGuard = true): bool
    {
        if ($enforceGuard) {
            $this->safeModeGuard->enforce(
                router: $router,
                action: DestructiveAction::BulkDeleteVouchers,
                endpoint: '/ip/hotspot/user/remove',
                payload: ['username' => $username],
                bypassToken: $bypassToken
            );
        }

        $driver = $this->connectionService->getDriver($router);

        // Find internal ID
        $find = $driver->command('/ip/hotspot/user/print', [
            '?name='.$username,
        ]);

        if (empty($find) || ! isset($find[0]['.id'])) {
            if (str_starts_with($username, '*')) {
                $driver->command('/ip/hotspot/user/remove', [
                    '=.id='.$username,
                ]);
                Cache::forget("ros_hotspot_users_{$router->id}");
                Cache::forget("ros_active_users_{$router->id}");
                Cache::forget("router_active_sessions_{$router->id}");

                return true;
            }

            return false;
        }

        $id = $find[0]['.id'];
        $driver->command('/ip/hotspot/user/remove', [
            '=.id='.$id,
        ]);

        Cache::forget("ros_hotspot_users_{$router->id}");
        Cache::forget("ros_active_users_{$router->id}");
        Cache::forget("router_active_sessions_{$router->id}");

        return true;
    }

    /**
     * Retrieve all configured Hotspot Users from MikroTik (/ip/hotspot/user).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getHotspotUsers(Router $router, bool $fresh = false): array
    {
        $cacheKey = "ros_hotspot_users_{$router->id}";
        if (! $fresh && Cache::has($cacheKey)) {
            $cached = Cache::get($cacheKey);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $driver = $this->connectionService->getDriver($router);
        $res = $driver->command('/ip/hotspot/user/print');

        $formatBytes = function (int $bytes): string {
            if ($bytes >= 1073741824) {
                return round($bytes / 1073741824, 2).' GB';
            }
            if ($bytes >= 1048576) {
                return round($bytes / 1048576, 2).' MB';
            }
            if ($bytes >= 1024) {
                return round($bytes / 1024, 1).' KB';
            }

            return $bytes.' B';
        };

        $users = [];
        foreach ($res as $item) {
            if (! isset($item['name']) || empty($item['name'])) {
                continue;
            }

            $bIn = isset($item['bytes-in']) ? (int) $item['bytes-in'] : 0;
            $bOut = isset($item['bytes-out']) ? (int) $item['bytes-out'] : 0;
            $totalBytes = $bIn + $bOut;

            $users[] = [
                'id' => $item['.id'] ?? '',
                'name' => (string) $item['name'],
                'profile' => (string) ($item['profile'] ?? 'default'),
                'server' => (string) ($item['server'] ?? 'all'),
                'uptime' => (string) ($item['uptime'] ?? '0s'),
                'bytes_in' => $bIn,
                'bytes_out' => $bOut,
                'bytes_total' => $totalBytes,
                'bytes_in_formatted' => $formatBytes($bIn),
                'bytes_out_formatted' => $formatBytes($bOut),
                'bytes_total_formatted' => $formatBytes($totalBytes),
                'packets_in' => isset($item['packets-in']) ? (int) $item['packets-in'] : 0,
                'packets_out' => isset($item['packets-out']) ? (int) $item['packets-out'] : 0,
                'limit_uptime' => $item['limit-uptime'] ?? null,
                'limit_bytes_total' => isset($item['limit-bytes-total']) ? (int) $item['limit-bytes-total'] : null,
                'disabled' => ($item['disabled'] ?? 'false') === 'true',
                'dynamic' => ($item['dynamic'] ?? 'false') === 'true',
                'comment' => isset($item['comment']) && ! empty($item['comment']) ? (string) $item['comment'] : null,
            ];
        }

        Cache::put($cacheKey, $users, 5);

        return $users;
    }

    /**
     * High-efficiency bulk remove hotspot users from MikroTik.
     * Eliminates individual print queries and avoids round-trip socket saturation.
     *
     * @param  array<int, string>  $usernames
     */
    public function bulkRemoveHotspotUsers(Router $router, array $usernames = [], ?string $comment = null): int
    {
        if (empty($usernames) && empty($comment)) {
            return 0;
        }

        $driver = $this->connectionService->getDriver($router);
        $idsToRemove = [];

        // If batch comment is provided, query all IDs in ONE print command
        if (! empty($comment)) {
            $rosUsers = $driver->command('/ip/hotspot/user/print', [
                '?comment='.$comment,
            ]);
            foreach ($rosUsers as $u) {
                if (isset($u['.id'])) {
                    $idsToRemove[] = $u['.id'];
                }
            }
        } elseif (! empty($usernames)) {
            // Find users by matching against single print result
            $rosUsers = $driver->command('/ip/hotspot/user/print');
            $usernameSet = array_flip($usernames);
            foreach ($rosUsers as $u) {
                $name = $u['name'] ?? null;
                if ($name && isset($usernameSet[$name]) && isset($u['.id'])) {
                    $idsToRemove[] = $u['.id'];
                }
            }
        }

        if (empty($idsToRemove)) {
            return 0;
        }

        $removedCount = 0;
        foreach ($idsToRemove as $id) {
            try {
                $driver->command('/ip/hotspot/user/remove', [
                    '=.id='.$id,
                ]);
                $removedCount++;
            } catch (\Throwable) {
                // Continue removing remainder
            }
        }

        Cache::forget("ros_active_users_{$router->id}");
        Cache::forget("router_active_sessions_{$router->id}");

        return $removedCount;
    }

    /**
     * Kick active hotspot session from router.
     */
    public function kickActiveUser(Router $router, string $activeId, ?string $bypassToken = null): bool
    {
        $driver = $this->connectionService->getDriver($router);

        $driver->command('/ip/hotspot/active/remove', [
            '=.id='.$activeId,
        ]);

        Cache::forget("ros_active_users_{$router->id}");
        Cache::forget("router_active_sessions_{$router->id}");

        return true;
    }

    /**
     * Monitor Rx/Tx traffic of a specific interface (cached for 2 seconds to protect RouterOS CPU).
     *
     * @return array{rx_bps: int, tx_bps: int, interface: string}
     */
    public function getInterfaceTraffic(Router $router, string $interface = 'ether1', bool $fresh = false): array
    {
        $cacheKey = "ros_traffic_{$router->id}_{$interface}";
        if (! $fresh && Cache::has($cacheKey)) {
            $cached = Cache::get($cacheKey);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $driver = $this->connectionService->getDriver($router);

        $res = $driver->command('/interface/monitor-traffic', [
            '=interface='.$interface,
            '=once=',
        ]);

        $item = $res[0] ?? [];
        $result = [
            'interface' => $interface,
            'rx_bps' => isset($item['rx-bits-per-second']) ? (int) $item['rx-bits-per-second'] : 0,
            'tx_bps' => isset($item['tx-bits-per-second']) ? (int) $item['tx-bits-per-second'] : 0,
        ];

        Cache::put($cacheKey, $result, 2);

        return $result;
    }

    /**
     * Retrieve network interfaces from the router with hardware fallback.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getInterfaces(Router $router, bool $fresh = false): array
    {
        $cacheKey = "ros_interfaces_{$router->id}";
        if (! $fresh && Cache::has($cacheKey)) {
            $cached = Cache::get($cacheKey);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $interfaces = [];

        try {
            $driver = $this->connectionService->getDriver($router);
            $res = $driver->command('/interface/print');

            foreach ($res as $item) {
                if (! isset($item['name']) || empty($item['name'])) {
                    continue;
                }

                $name = (string) $item['name'];
                $type = (string) ($item['type'] ?? 'ether');
                $comment = isset($item['comment']) && ! empty($item['comment']) ? (string) $item['comment'] : null;
                $running = ($item['running'] ?? 'false') === 'true';
                $disabled = ($item['disabled'] ?? 'false') === 'true';

                $interfaces[] = [
                    'name' => $name,
                    'type' => $type,
                    'comment' => $comment,
                    'running' => $running,
                    'disabled' => $disabled,
                    'label' => $comment ? "{$name} ({$comment})" : $name,
                ];
            }
        } catch (Exception) {
            // Ignore socket/connection error and fall back to device-specific list
        }

        if (empty($interfaces)) {
            $defaults = $this->getDefaultInterfacesForRouter($router);
            foreach ($defaults as $item) {
                $interfaces[] = [
                    'name' => $item['name'],
                    'type' => $item['type'] ?? 'ether',
                    'comment' => $item['comment'] ?? null,
                    'running' => true,
                    'disabled' => false,
                    'label' => isset($item['comment']) ? "{$item['name']} ({$item['comment']})" : $item['name'],
                ];
            }
        }

        Cache::put($cacheKey, $interfaces, 15);

        return $interfaces;
    }

    /**
     * Generate fallback interfaces tailored to the router's hardware model.
     *
     * @return array<int, array{name: string, type: string, comment?: string}>
     */
    public function getDefaultInterfacesForRouter(Router $router): array
    {
        $model = strtoupper((string) ($router->board_model ?? $router->name ?? ''));

        // HEX / RB750Gr3 / hEX models (5 GigE ports, no SFP, no wireless)
        if (str_contains($model, 'HEX') || str_contains($model, '750') || str_contains($model, 'GR3')) {
            return [
                ['name' => 'ether1', 'type' => 'ether', 'comment' => 'WAN Gateway'],
                ['name' => 'ether2', 'type' => 'ether', 'comment' => 'LAN / Hotspot'],
                ['name' => 'ether3', 'type' => 'ether'],
                ['name' => 'ether4', 'type' => 'ether'],
                ['name' => 'ether5', 'type' => 'ether'],
            ];
        }

        // RB4011 / RB3011 / RB2011 (10 GigE ports + 1 SFP+)
        if (str_contains($model, '4011') || str_contains($model, '3011') || str_contains($model, '2011')) {
            $list = [];
            for ($i = 1; $i <= 10; $i++) {
                $list[] = [
                    'name' => "ether{$i}",
                    'type' => 'ether',
                    'comment' => $i === 1 ? 'WAN Gateway' : ($i === 2 ? 'LAN' : null),
                ];
            }
            $list[] = ['name' => 'sfp-sfpplus1', 'type' => 'sfp', 'comment' => 'Fiber SFP+'];

            return $list;
        }

        // CCR / Cloud Core (e.g. CCR1009, CCR2004)
        if (str_contains($model, 'CCR')) {
            return [
                ['name' => 'ether1', 'type' => 'ether', 'comment' => 'WAN'],
                ['name' => 'ether2', 'type' => 'ether'],
                ['name' => 'sfp-sfpplus1', 'type' => 'sfp', 'comment' => 'SFP+ Fiber 1'],
                ['name' => 'sfp-sfpplus2', 'type' => 'sfp', 'comment' => 'SFP+ Fiber 2'],
            ];
        }

        // hAP / cAP wireless models
        if (str_contains($model, 'HAP') || str_contains($model, 'CAP') || str_contains($model, 'WAP')) {
            return [
                ['name' => 'ether1', 'type' => 'ether', 'comment' => 'WAN / PoE In'],
                ['name' => 'ether2', 'type' => 'ether', 'comment' => 'LAN'],
                ['name' => 'wlan1', 'type' => 'wlan', 'comment' => '2.4GHz Wi-Fi'],
                ['name' => 'wlan2', 'type' => 'wlan', 'comment' => '5GHz Wi-Fi'],
            ];
        }

        // Standard 5-port Gigabit default
        return [
            ['name' => 'ether1', 'type' => 'ether', 'comment' => 'WAN Gateway'],
            ['name' => 'ether2', 'type' => 'ether', 'comment' => 'LAN / Hotspot'],
            ['name' => 'ether3', 'type' => 'ether'],
            ['name' => 'ether4', 'type' => 'ether'],
            ['name' => 'ether5', 'type' => 'ether'],
        ];
    }
}
