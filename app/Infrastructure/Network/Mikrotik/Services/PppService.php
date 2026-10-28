<?php

declare(strict_types=1);

namespace App\Infrastructure\Network\Mikrotik\Services;

use App\Application\SafeMode\Services\MikrotikSafeModeGuard;
use App\Domain\SafeMode\Enums\DestructiveAction;
use App\Models\Router;
use Exception;
use Illuminate\Support\Facades\Cache;

class PppService
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
     * Retrieve PPP profiles from MikroTik router (/ppp/profile/print).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getProfiles(Router $router, bool $fresh = false): array
    {
        $cacheKey = "ros_ppp_profiles_{$router->id}";
        if (! $fresh && Cache::has($cacheKey)) {
            $cached = Cache::get($cacheKey);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $driver = $this->connectionService->getDriver($router);
        $res = $driver->command('/ppp/profile/print');

        $profiles = [];
        foreach ($res as $item) {
            if (! isset($item['name']) || empty($item['name'])) {
                continue;
            }

            $onlyOne = $item['only-one'] ?? 'default';
            $profiles[] = [
                'id' => $item['.id'] ?? '',
                'name' => (string) $item['name'],
                'local_address' => $item['local-address'] ?? null,
                'remote_address' => $item['remote-address'] ?? null,
                'rate_limit' => $item['rate-limit'] ?? null,
                'only_one' => $onlyOne === 'yes' || $onlyOne === 'true',
                'dns_server' => $item['dns-server'] ?? null,
                'comment' => isset($item['comment']) && ! empty($item['comment']) ? (string) $item['comment'] : null,
                'default' => ($item['default'] ?? 'false') === 'true',
            ];
        }

        Cache::put($cacheKey, $profiles, 15);

        return $profiles;
    }

    /**
     * Add a new PPP bandwidth profile to MikroTik.
     *
     * @param  array{name: string, local_address?: string|null, remote_address?: string|null, rate_limit?: string|null, only_one?: bool|null, dns_server?: string|null, comment?: string|null}  $data
     * @return array<int, array<string, string>>
     */
    public function addProfile(Router $router, array $data): array
    {
        $driver = $this->connectionService->getDriver($router);

        $params = [
            '=name='.$data['name'],
        ];

        if (! empty($data['local_address'])) {
            $params[] = '=local-address='.$data['local_address'];
        }

        if (! empty($data['remote_address'])) {
            $params[] = '=remote-address='.$data['remote_address'];
        }

        if (! empty($data['rate_limit'])) {
            $params[] = '=rate-limit='.$data['rate_limit'];
        }

        if (isset($data['only_one'])) {
            $params[] = '=only-one='.($data['only_one'] ? 'yes' : 'no');
        }

        if (! empty($data['dns_server'])) {
            $params[] = '=dns-server='.$data['dns_server'];
        }

        if (! empty($data['comment'])) {
            $params[] = '=comment='.$data['comment'];
        }

        $res = $driver->command('/ppp/profile/add', $params);
        Cache::forget("ros_ppp_profiles_{$router->id}");

        return $res;
    }

    /**
     * Update an existing PPP profile on MikroTik.
     *
     * @param  array{name?: string, local_address?: string|null, remote_address?: string|null, rate_limit?: string|null, only_one?: bool|null, dns_server?: string|null, comment?: string|null}  $data
     * @return array<int, array<string, string>>
     */
    public function updateProfile(Router $router, string $profileName, array $data): array
    {
        $driver = $this->connectionService->getDriver($router);

        $find = $driver->command('/ppp/profile/print', [
            '?name='.$profileName,
        ]);

        if (empty($find) || ! isset($find[0]['.id'])) {
            throw new Exception("PPP profile '{$profileName}' not found on router.");
        }

        $id = $find[0]['.id'];
        $params = ['=.id='.$id];

        if (! empty($data['name']) && $data['name'] !== $profileName) {
            $params[] = '=name='.$data['name'];
        }

        if (array_key_exists('local_address', $data)) {
            $params[] = '=local-address='.($data['local_address'] ?? '');
        }

        if (array_key_exists('remote_address', $data)) {
            $params[] = '=remote-address='.($data['remote_address'] ?? '');
        }

        if (array_key_exists('rate_limit', $data)) {
            $params[] = '=rate-limit='.($data['rate_limit'] ?? '');
        }

        if (isset($data['only_one'])) {
            $params[] = '=only-one='.($data['only_one'] ? 'yes' : 'no');
        }

        if (array_key_exists('dns_server', $data)) {
            $params[] = '=dns-server='.($data['dns_server'] ?? '');
        }

        if (array_key_exists('comment', $data)) {
            $params[] = '=comment='.($data['comment'] ?? '');
        }

        $res = $driver->command('/ppp/profile/set', $params);
        Cache::forget("ros_ppp_profiles_{$router->id}");

        return $res;
    }

    /**
     * Remove a PPP profile from MikroTik (Safe Mode protected).
     */
    public function removeProfile(Router $router, string $profileName, ?string $bypassToken = null, bool $enforceGuard = true): bool
    {
        if ($enforceGuard) {
            $this->safeModeGuard->enforce(
                router: $router,
                action: DestructiveAction::BulkDeletePppProfiles,
                endpoint: '/ppp/profile/remove',
                payload: ['profile' => $profileName],
                bypassToken: $bypassToken
            );
        }

        $driver = $this->connectionService->getDriver($router);

        $find = $driver->command('/ppp/profile/print', [
            '?name='.$profileName,
        ]);

        if (empty($find) || ! isset($find[0]['.id'])) {
            return false;
        }

        $id = $find[0]['.id'];
        $driver->command('/ppp/profile/remove', [
            '=.id='.$id,
        ]);

        Cache::forget("ros_ppp_profiles_{$router->id}");

        return true;
    }

    /**
     * Retrieve all PPP Secrets from MikroTik router (/ppp/secret/print).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getSecrets(Router $router, bool $fresh = false): array
    {
        $cacheKey = "ros_ppp_secrets_{$router->id}";
        if (! $fresh && Cache::has($cacheKey)) {
            $cached = Cache::get($cacheKey);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $driver = $this->connectionService->getDriver($router);
        $res = $driver->command('/ppp/secret/print');

        $secrets = [];
        foreach ($res as $item) {
            if (! isset($item['name']) || empty($item['name'])) {
                continue;
            }

            $disabledVal = $item['disabled'] ?? 'false';
            $isDisabled = $disabledVal === 'true' || $disabledVal === 'yes';

            $secrets[] = [
                'id' => $item['.id'] ?? '',
                'name' => (string) $item['name'],
                'password' => (string) ($item['password'] ?? ''),
                'service' => (string) ($item['service'] ?? 'any'),
                'profile' => (string) ($item['profile'] ?? 'default'),
                'local_address' => $item['local-address'] ?? null,
                'remote_address' => $item['remote-address'] ?? null,
                'caller_id' => $item['caller-id'] ?? null,
                'routes' => $item['routes'] ?? null,
                'limit_bytes_in' => isset($item['limit-bytes-in']) ? (int) $item['limit-bytes-in'] : null,
                'limit_bytes_out' => isset($item['limit-bytes-out']) ? (int) $item['limit-bytes-out'] : null,
                'disabled' => $isDisabled,
                'comment' => isset($item['comment']) && ! empty($item['comment']) ? (string) $item['comment'] : null,
                'last_logged_out' => $item['last-logged-out'] ?? null,
            ];
        }

        Cache::put($cacheKey, $secrets, 5);

        return $secrets;
    }

    /**
     * Add a dedicated PPP secret to MikroTik.
     *
     * @param  array{name: string, password: string, service?: string|null, profile?: string|null, local_address?: string|null, remote_address?: string|null, caller_id?: string|null, routes?: string|null, comment?: string|null, disabled?: bool|null}  $data
     * @return array<int, array<string, string>>
     */
    public function addSecret(Router $router, array $data): array
    {
        $driver = $this->connectionService->getDriver($router);

        $params = [
            '=name='.$data['name'],
            '=password='.$data['password'],
            '=service='.($data['service'] ?? 'pppoe'),
            '=profile='.($data['profile'] ?? 'default'),
        ];

        if (! empty($data['local_address'])) {
            $params[] = '=local-address='.$data['local_address'];
        }

        if (! empty($data['remote_address'])) {
            $params[] = '=remote-address='.$data['remote_address'];
        }

        if (! empty($data['caller_id'])) {
            $params[] = '=caller-id='.$data['caller_id'];
        }

        if (! empty($data['routes'])) {
            $params[] = '=routes='.$data['routes'];
        }

        if (! empty($data['comment'])) {
            $params[] = '=comment='.$data['comment'];
        }

        if (isset($data['disabled'])) {
            $params[] = '=disabled='.($data['disabled'] ? 'yes' : 'no');
        }

        $res = $driver->command('/ppp/secret/add', $params);
        Cache::forget("ros_ppp_secrets_{$router->id}");

        return $res;
    }

    /**
     * Disconnect/kick all active PPP connections for a specific username.
     */
    public function disconnectActiveUser(Router $router, string $username): int
    {
        $driver = $this->connectionService->getDriver($router);
        $activeList = $driver->command('/ppp/active/print', [
            '?name='.$username,
        ]);

        $kicked = 0;
        foreach ($activeList as $active) {
            if (isset($active['.id'])) {
                try {
                    $driver->command('/ppp/active/remove', ['=.id='.$active['.id']]);
                    $kicked++;
                } catch (\Throwable) {
                }
            }
        }

        Cache::forget("ros_ppp_active_{$router->id}");

        return $kicked;
    }

    /**
     * Update an existing PPP secret on MikroTik.
     * If the secret does not exist on the router, it will automatically be created.
     *
     * @param  array{password?: string, service?: string, profile?: string, local_address?: string|null, remote_address?: string|null, caller_id?: string|null, routes?: string|null, comment?: string|null, disabled?: bool|null}  $data
     * @return array<int, array<string, string>>
     */
    public function updateSecret(Router $router, string $username, array $data): array
    {
        $driver = $this->connectionService->getDriver($router);

        $find = $driver->command('/ppp/secret/print', [
            '?name='.$username,
        ]);

        if (empty($find) || ! isset($find[0]['.id'])) {
            $created = $this->addSecret($router, [
                'name' => $username,
                'password' => $data['password'] ?? '',
                'service' => $data['service'] ?? 'pppoe',
                'profile' => $data['profile'] ?? 'default',
                'local_address' => $data['local_address'] ?? null,
                'remote_address' => $data['remote_address'] ?? null,
                'caller_id' => $data['caller_id'] ?? null,
                'routes' => $data['routes'] ?? null,
                'comment' => $data['comment'] ?? null,
                'disabled' => $data['disabled'] ?? false,
            ]);

            $this->disconnectActiveUser($router, $username);
            Cache::forget("ros_ppp_secrets_{$router->id}");

            return $created;
        }

        $id = $find[0]['.id'];
        $params = ['=.id='.$id];

        if (! empty($data['password'])) {
            $params[] = '=password='.$data['password'];
        }

        if (! empty($data['service'])) {
            $params[] = '=service='.$data['service'];
        }

        if (! empty($data['profile'])) {
            $params[] = '=profile='.$data['profile'];
        }

        if (array_key_exists('local_address', $data)) {
            $params[] = '=local-address='.($data['local_address'] ?? '');
        }

        if (array_key_exists('remote_address', $data)) {
            $params[] = '=remote-address='.($data['remote_address'] ?? '');
        }

        if (array_key_exists('caller_id', $data)) {
            $params[] = '=caller-id='.($data['caller_id'] ?? '');
        }

        if (array_key_exists('routes', $data)) {
            $params[] = '=routes='.($data['routes'] ?? '');
        }

        if (array_key_exists('comment', $data)) {
            $params[] = '=comment='.($data['comment'] ?? '');
        }

        if (isset($data['disabled'])) {
            $params[] = '=disabled='.($data['disabled'] ? 'yes' : 'no');
        }

        $res = $driver->command('/ppp/secret/set', $params);
        $this->disconnectActiveUser($router, $username);
        Cache::forget("ros_ppp_secrets_{$router->id}");

        return $res;
    }

    /**
     * Toggle disabled status of a PPP secret on MikroTik.
     */
    public function toggleSecret(Router $router, string $username, bool $disabled): bool
    {
        $driver = $this->connectionService->getDriver($router);

        $find = $driver->command('/ppp/secret/print', [
            '?name='.$username,
        ]);

        if (empty($find) || ! isset($find[0]['.id'])) {
            return false;
        }

        $id = $find[0]['.id'];
        $driver->command('/ppp/secret/set', [
            '=.id='.$id,
            '=disabled='.($disabled ? 'yes' : 'no'),
        ]);

        // If secret was disabled, also kick any active session immediately so the cut-off takes effect
        if ($disabled) {
            $activeList = $driver->command('/ppp/active/print', [
                '?name='.$username,
            ]);
            foreach ($activeList as $active) {
                if (isset($active['.id'])) {
                    try {
                        $driver->command('/ppp/active/remove', ['=.id='.$active['.id']]);
                    } catch (\Throwable) {
                    }
                }
            }
            Cache::forget("ros_ppp_active_{$router->id}");
        }

        Cache::forget("ros_ppp_secrets_{$router->id}");

        return true;
    }

    /**
     * Remove a PPP secret from MikroTik (Safe Mode protected).
     */
    public function removeSecret(Router $router, string $username, ?string $bypassToken = null, bool $enforceGuard = true): bool
    {
        if ($enforceGuard) {
            $this->safeModeGuard->enforce(
                router: $router,
                action: DestructiveAction::BulkDeletePppSecrets,
                endpoint: '/ppp/secret/remove',
                payload: ['username' => $username],
                bypassToken: $bypassToken
            );
        }

        $driver = $this->connectionService->getDriver($router);

        $find = $driver->command('/ppp/secret/print', [
            '?name='.$username,
        ]);

        if (empty($find) || ! isset($find[0]['.id'])) {
            return false;
        }

        $id = $find[0]['.id'];

        // Disconnect active session if connected
        $activeList = $driver->command('/ppp/active/print', [
            '?name='.$username,
        ]);
        foreach ($activeList as $active) {
            if (isset($active['.id'])) {
                try {
                    $driver->command('/ppp/active/remove', ['=.id='.$active['.id']]);
                } catch (\Throwable) {
                }
            }
        }

        $driver->command('/ppp/secret/remove', [
            '=.id='.$id,
        ]);

        Cache::forget("ros_ppp_secrets_{$router->id}");
        Cache::forget("ros_ppp_active_{$router->id}");

        return true;
    }

    /**
     * Get active PPP connections from router (cached for 3 seconds).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getActiveConnections(Router $router, bool $fresh = false): array
    {
        $cacheKey = "ros_ppp_active_{$router->id}";
        if (! $fresh && Cache::has($cacheKey)) {
            $cached = Cache::get($cacheKey);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $driver = $this->connectionService->getDriver($router);
        $res = $driver->command('/ppp/active/print');

        $active = [];
        foreach ($res as $item) {
            if (! isset($item['name']) || empty($item['name'])) {
                continue;
            }

            $active[] = [
                'id' => $item['.id'] ?? '',
                'name' => (string) $item['name'],
                'service' => (string) ($item['service'] ?? 'pppoe'),
                'caller_id' => (string) ($item['caller-id'] ?? '-'),
                'address' => (string) ($item['address'] ?? '-'),
                'uptime' => (string) ($item['uptime'] ?? '0s'),
                'encoding' => (string) ($item['encoding'] ?? '-'),
                'session_id' => (string) ($item['session-id'] ?? '-'),
                'radius' => ($item['radius'] ?? 'false') === 'true',
            ];
        }

        Cache::put($cacheKey, $active, 3);

        return $active;
    }

    /**
     * Disconnect/kick an active PPP connection from MikroTik.
     */
    public function kickActiveConnection(Router $router, string $activeId, ?string $bypassToken = null): bool
    {
        $driver = $this->connectionService->getDriver($router);

        $driver->command('/ppp/active/remove', [
            '=.id='.$activeId,
        ]);

        Cache::forget("ros_ppp_active_{$router->id}");

        return true;
    }

    /**
     * Retrieve IP Pools from MikroTik (/ip/pool/print) for selection.
     *
     * @return array<int, array{name: string, ranges: string}>
     */
    public function getIpPools(Router $router, bool $fresh = false): array
    {
        $cacheKey = "ros_ip_pools_{$router->id}";
        if (! $fresh && Cache::has($cacheKey)) {
            $cached = Cache::get($cacheKey);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $pools = [];
        try {
            $driver = $this->connectionService->getDriver($router);
            $res = $driver->command('/ip/pool/print');

            foreach ($res as $item) {
                if (! isset($item['name']) || empty($item['name'])) {
                    continue;
                }

                $pools[] = [
                    'name' => (string) $item['name'],
                    'ranges' => (string) ($item['ranges'] ?? ''),
                ];
            }
        } catch (\Throwable) {
            // Pool query failure is non-fatal
        }

        Cache::put($cacheKey, $pools, 30);

        return $pools;
    }
}
