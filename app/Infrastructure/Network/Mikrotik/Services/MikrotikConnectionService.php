<?php

declare(strict_types=1);

namespace App\Infrastructure\Network\Mikrotik\Services;

use App\Domain\Router\Enums\RouterProtocol;
use App\Domain\Router\Enums\RouterStatus;
use App\Infrastructure\Network\Mikrotik\Contracts\MikrotikDriverInterface;
use App\Infrastructure\Network\Mikrotik\Drivers\RestRouterOsDriver;
use App\Infrastructure\Network\Mikrotik\Drivers\SocketRouterOsDriver;
use App\Models\Router;
use Exception;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class MikrotikConnectionService
{
    public const MAX_RETRIES = 3;

    public const COOLDOWN_SECONDS = 60;

    /** @var array<int, MikrotikDriverInterface> */
    protected array $activeConnections = [];

    /**
     * Check if connection retry circuit is open (router offline and in cooldown).
     */
    public function isCircuitOpen(Router $router): bool
    {
        $failures = $this->getConsecutiveFailures($router);
        if ($failures < self::MAX_RETRIES) {
            return false;
        }

        $lastFailure = Cache::get($this->getLastFailureCacheKey($router));
        if (! $lastFailure) {
            return false;
        }

        return (Carbon::now()->timestamp - (int) $lastFailure) < self::COOLDOWN_SECONDS;
    }

    /**
     * Get consecutive connection failure count for router.
     */
    public function getConsecutiveFailures(Router $router): int
    {
        return (int) Cache::get($this->getFailureCountCacheKey($router), 0);
    }

    /**
     * Record a connection failure and update router status to offline if threshold reached.
     */
    public function recordFailure(Router $router): int
    {
        $count = $this->getConsecutiveFailures($router) + 1;
        Cache::put($this->getFailureCountCacheKey($router), $count, 3600);
        Cache::put($this->getLastFailureCacheKey($router), Carbon::now()->timestamp, 3600);

        if ($count >= self::MAX_RETRIES) {
            $router->update(['status' => RouterStatus::Offline->value]);
        }

        return $count;
    }

    /**
     * Reset consecutive failure count upon successful connection or manual test.
     */
    public function resetFailures(Router $router): void
    {
        Cache::forget($this->getFailureCountCacheKey($router));
        Cache::forget($this->getLastFailureCacheKey($router));
    }

    /**
     * Get or establish an active connection driver for the given router.
     * Throws immediately if the circuit breaker is open to prevent timeout hangs.
     */
    public function getDriver(Router $router, bool $force = false): MikrotikDriverInterface
    {
        if (isset($this->activeConnections[$router->id]) && $this->activeConnections[$router->id]->isConnected()) {
            return $this->activeConnections[$router->id];
        }

        // Fast-fail if router has failed 3+ times consecutively and cooldown hasn't elapsed
        if (! $force && $this->isCircuitOpen($router)) {
            $failures = $this->getConsecutiveFailures($router);
            throw new Exception("Router {$router->name} is offline. Polling paused after {$failures} consecutive failed attempts. Please retry manually or wait for cooldown.");
        }

        $protocol = RouterProtocol::tryFrom($router->protocol) ?? RouterProtocol::Socket;
        $driver = match ($protocol) {
            RouterProtocol::RestHttps => new RestRouterOsDriver,
            default => new SocketRouterOsDriver,
        };

        $plainPassword = $router->getDecryptedPassword();
        $useTls = $router->use_tls || $protocol === RouterProtocol::SocketSsl || $protocol === RouterProtocol::RestHttps;

        try {
            $driver->connect(
                host: $router->host,
                port: $router->api_port,
                username: $router->username,
                password: $plainPassword,
                timeout: $router->timeout_seconds,
                useTls: $useTls
            );

            // Successfully connected - reset failure count
            $this->resetFailures($router);
            $this->activeConnections[$router->id] = $driver;

            return $driver;
        } catch (\Throwable $e) {
            $this->recordFailure($router);
            throw $e;
        }
    }

    /**
     * Test connection and retrieve live device telemetry, updating database metadata.
     *
     * @return array<string, mixed>
     */
    public function testAndSyncTelemetry(Router $router, bool $force = false): array
    {
        $cacheKey = "router_telemetry_res_{$router->id}";
        if (! $force && Cache::has($cacheKey)) {
            $cached = Cache::get($cacheKey);
            if (is_array($cached)) {
                return $cached;
            }
        }

        if (! $force && $this->isCircuitOpen($router)) {
            $failures = $this->getConsecutiveFailures($router);

            return [
                'success' => false,
                'status' => RouterStatus::Offline->value,
                'error' => "Router is offline. Checked {$failures} times. Polling paused to prevent connection timeout.",
                'consecutive_failures' => $failures,
                'is_circuit_open' => true,
            ];
        }

        try {
            $driver = $this->getDriver($router, $force);
            $resource = $driver->getSystemResource();

            $cpu = isset($resource['cpu-load']) ? (int) $resource['cpu-load'] : (isset($resource['cpu_load']) ? (int) $resource['cpu_load'] : null);
            $freeMem = isset($resource['free-memory']) ? (int) $resource['free-memory'] : (isset($resource['free_memory']) ? (int) $resource['free_memory'] : null);
            $totalMem = isset($resource['total-memory']) ? (int) $resource['total-memory'] : (isset($resource['total_memory']) ? (int) $resource['total_memory'] : null);
            $freeHdd = isset($resource['free-hdd-space']) ? (int) $resource['free-hdd-space'] : (isset($resource['free_hdd_space']) ? (int) $resource['free_hdd_space'] : null);
            $totalHdd = isset($resource['total-hdd-space']) ? (int) $resource['total-hdd-space'] : (isset($resource['total_hdd_space']) ? (int) $resource['total_hdd_space'] : null);
            $uptime = $resource['uptime'] ?? null;
            $version = $resource['version'] ?? null;
            $board = $resource['board-name'] ?? $resource['board_name'] ?? null;
            $arch = $resource['architecture-name'] ?? $resource['architecture_name'] ?? null;
            $cpuFreq = isset($resource['cpu-frequency']) ? (int) $resource['cpu-frequency'] : (isset($resource['cpu_frequency']) ? (int) $resource['cpu_frequency'] : null);
            $cpuCount = isset($resource['cpu-count']) ? (int) $resource['cpu-count'] : (isset($resource['cpu_count']) ? (int) $resource['cpu_count'] : 1);

            $this->resetFailures($router);

            // Throttle database writes: only write if status changed or 30s elapsed to reduce DB I/O
            $needsDbUpdate = $force
                || $router->status !== RouterStatus::Online->value
                || $router->last_connected_at === null
                || Carbon::now()->diffInSeconds($router->last_connected_at) >= 30;

            if ($needsDbUpdate) {
                $router->update([
                    'status' => RouterStatus::Online->value,
                    'last_connected_at' => Carbon::now(),
                    'ros_version' => $version,
                    'board_model' => $board,
                    'cpu_load' => $cpu,
                    'free_memory_bytes' => $freeMem,
                    'total_memory_bytes' => $totalMem,
                    'uptime' => $uptime,
                ]);
            }

            // Normalise telemetry with both snake_case and original RouterOS keys
            $normalizedResource = array_merge($resource, [
                'uptime' => $uptime,
                'version' => $version,
                'cpu_load' => $cpu,
                'free_memory' => $freeMem,
                'total_memory' => $totalMem,
                'free_hdd_space' => $freeHdd,
                'total_hdd_space' => $totalHdd,
                'board_name' => $board,
                'architecture_name' => $arch,
                'cpu_frequency' => $cpuFreq,
                'cpu_count' => $cpuCount,
            ]);

            $result = [
                'success' => true,
                'status' => RouterStatus::Online->value,
                'resource' => $normalizedResource,
                'consecutive_failures' => 0,
                'is_circuit_open' => false,
            ];

            // Cache telemetry for 3 seconds to avoid duplicate socket handshakes
            Cache::put($cacheKey, $result, 3);

            return $result;
        } catch (Exception $e) {
            $failures = $this->recordFailure($router);

            $router->update([
                'status' => RouterStatus::Offline->value,
            ]);

            Cache::forget($cacheKey);

            return [
                'success' => false,
                'status' => RouterStatus::Offline->value,
                'error' => $e->getMessage(),
                'consecutive_failures' => $failures,
                'is_circuit_open' => $failures >= self::MAX_RETRIES,
            ];
        }
    }

    /**
     * Explicitly close connection for a router.
     */
    public function close(Router $router): void
    {
        if (isset($this->activeConnections[$router->id])) {
            $this->activeConnections[$router->id]->disconnect();
            unset($this->activeConnections[$router->id]);
        }
    }

    /**
     * Close all active sockets across all routers.
     */
    public function closeAll(): void
    {
        foreach ($this->activeConnections as $id => $driver) {
            try {
                $driver->disconnect();
            } catch (\Throwable) {
                // Ignore disconnect errors during teardown
            }
            unset($this->activeConnections[$id]);
        }
    }

    public function __destruct()
    {
        $this->closeAll();
    }

    protected function getFailureCountCacheKey(Router $router): string
    {
        return "router_conn_failures_{$router->id}";
    }

    protected function getLastFailureCacheKey(Router $router): string
    {
        return "router_conn_last_fail_{$router->id}";
    }
}
