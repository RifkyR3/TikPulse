<?php

declare(strict_types=1);

namespace App\Infrastructure\Network\Mikrotik\Contracts;

interface MikrotikDriverInterface
{
    /**
     * Connect and authenticate to the MikroTik RouterOS device.
     */
    public function connect(
        string $host,
        int $port,
        string $username,
        string $password,
        int $timeout = 5,
        bool $useTls = false
    ): void;

    /**
     * Terminate the connection.
     */
    public function disconnect(): void;

    /**
     * Check if the driver is currently connected.
     */
    public function isConnected(): bool;

    /**
     * Send a command sentence and return parsed response array.
     *
     * @param  string  $command  e.g. '/system/resource/print'
     * @param  array<string, string>  $words  e.g. ['=name=user1', '=profile=default']
     * @return array<int, array<string, string>>
     */
    public function command(string $command, array $words = []): array;

    /**
     * Send a lightweight ping / query to test connection viability.
     */
    public function ping(): bool;

    /**
     * Query system resource data (CPU, memory, uptime, version, board name).
     *
     * @return array<string, mixed>
     */
    public function getSystemResource(): array;
}
