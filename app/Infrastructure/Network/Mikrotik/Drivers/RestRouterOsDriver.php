<?php

declare(strict_types=1);

namespace App\Infrastructure\Network\Mikrotik\Drivers;

use App\Domain\Router\Exceptions\RouterConnectionException;
use App\Infrastructure\Network\Mikrotik\Contracts\MikrotikDriverInterface;
use Exception;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class RestRouterOsDriver implements MikrotikDriverInterface
{
    protected ?PendingRequest $client = null;

    protected string $baseUrl = '';

    protected bool $connected = false;

    protected string $host = '';

    protected int $port = 443;

    public function connect(
        string $host,
        int $port,
        string $username,
        string $password,
        int $timeout = 5,
        bool $useTls = true
    ): void {
        $this->host = $host;
        $this->port = $port;

        $scheme = $useTls ? 'https' : 'http';
        $this->baseUrl = "{$scheme}://{$host}:{$port}/rest";

        $this->client = Http::baseUrl($this->baseUrl)
            ->withBasicAuth($username, $password)
            ->timeout($timeout)
            ->withoutVerifying(); // Allow self-signed router certificates

        if (! $this->ping()) {
            throw new RouterConnectionException(
                $host,
                $port,
                "Unable to establish REST API session to RouterOS on {$this->baseUrl}."
            );
        }

        $this->connected = true;
    }

    public function disconnect(): void
    {
        $this->client = null;
        $this->connected = false;
    }

    public function isConnected(): bool
    {
        return $this->connected && $this->client !== null;
    }

    /**
     * @param  array<string, mixed>  $words
     * @return array<int, array<string, string>>
     */
    public function command(string $command, array $words = []): array
    {
        if (! $this->isConnected()) {
            throw new RouterConnectionException($this->host, $this->port, 'REST driver is not connected.');
        }

        // Clean command path e.g. /system/resource/print -> /system/resource
        $endpoint = trim(str_replace('/print', '', $command), '/');

        // Extract key-value pairs
        $payload = [];
        foreach ($words as $key => $val) {
            if (is_string($key)) {
                $payload[$key] = $val;
            } elseif (is_string($val) && str_starts_with($val, '=')) {
                $parts = explode('=', substr($val, 1), 2);
                $payload[$parts[0]] = $parts[1] ?? '';
            }
        }

        try {
            if (empty($payload)) {
                $response = $this->client->get("/{$endpoint}");
            } else {
                $response = $this->client->put("/{$endpoint}", $payload);
            }

            if (! $response->successful()) {
                throw new Exception($response->body());
            }

            $data = $response->json();
            if (is_array($data)) {
                // If single associative array, wrap in outer array
                if (array_keys($data) !== range(0, count($data) - 1)) {
                    return [$data];
                }

                return $data;
            }

            return [];
        } catch (Exception $e) {
            throw new RouterConnectionException(
                $this->host,
                $this->port,
                "MikroTik REST API Error: {$e->getMessage()}"
            );
        }
    }

    public function ping(): bool
    {
        if ($this->client === null) {
            return false;
        }

        try {
            $res = $this->client->get('/system/identity');

            return $res->successful();
        } catch (Exception) {
            return false;
        }
    }

    public function getSystemResource(): array
    {
        $res = $this->command('/system/resource');

        return $res[0] ?? [];
    }
}
