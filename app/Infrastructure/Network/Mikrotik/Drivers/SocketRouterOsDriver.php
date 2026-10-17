<?php

declare(strict_types=1);

namespace App\Infrastructure\Network\Mikrotik\Drivers;

use App\Domain\Router\Exceptions\RouterConnectionException;
use App\Infrastructure\Network\Mikrotik\Contracts\MikrotikDriverInterface;
use Exception;

class SocketRouterOsDriver implements MikrotikDriverInterface
{
    /** @var resource|null */
    protected $socket = null;

    protected bool $connected = false;

    protected string $host = '';

    protected int $port = 8728;

    public function connect(
        string $host,
        int $port,
        string $username,
        string $password,
        int $timeout = 5,
        bool $useTls = false
    ): void {
        $this->host = $host;
        $this->port = $port;

        $target = $useTls ? "ssl://{$host}:{$port}" : "tcp://{$host}:{$port}";
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
            ],
        ]);

        $errno = 0;
        $errstr = '';

        $socket = @stream_socket_client(
            $target,
            $errno,
            $errstr,
            (float) $timeout,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if (! is_resource($socket)) {
            throw new RouterConnectionException(
                $host,
                $port,
                "Unable to connect to MikroTik socket on {$target}: {$errstr} ({$errno})",
                $errstr
            );
        }

        stream_set_timeout($socket, $timeout);
        $this->socket = $socket;
        $this->connected = true;

        $this->authenticate($username, $password);
    }

    protected function authenticate(string $username, string $password): void
    {
        // Try RouterOS v6.43+ modern login first
        $response = $this->command('/login', [
            '=name='.$username,
            '=password='.$password,
        ]);

        // If challenge received (legacy RouterOS v6.42 or earlier)
        if (isset($response[0]['!trap'])) {
            $errorMsg = $response[0]['message'] ?? 'Authentication failed';
            $this->disconnect();
            throw new RouterConnectionException($this->host, $this->port, "MikroTik authentication failed: {$errorMsg}");
        }

        // If RouterOS requested challenge response
        if (isset($response[0]['ret'])) {
            $challenge = $response[0]['ret'];
            $md5Pass = md5(chr(0).$password.pack('H*', $challenge));
            $loginResponse = $this->command('/login', [
                '=name='.$username,
                '=response=00'.$md5Pass,
            ]);

            if (isset($loginResponse[0]['!trap'])) {
                $errorMsg = $loginResponse[0]['message'] ?? 'Authentication challenge failed';
                $this->disconnect();
                throw new RouterConnectionException($this->host, $this->port, "MikroTik authentication failed: {$errorMsg}");
            }
        }
    }

    public function disconnect(): void
    {
        if (is_resource($this->socket)) {
            @fclose($this->socket);
        }
        $this->socket = null;
        $this->connected = false;
    }

    public function isConnected(): bool
    {
        return $this->connected && is_resource($this->socket);
    }

    /**
     * @param  array<int, string>|array<string, string>  $words
     * @return array<int, array<string, string>>
     */
    public function command(string $command, array $words = []): array
    {
        if (! $this->isConnected()) {
            throw new RouterConnectionException($this->host, $this->port, 'Socket is not connected.');
        }

        $sentence = [$command];
        foreach ($words as $key => $val) {
            if (is_string($key) && ! str_starts_with($val, '=')) {
                $sentence[] = "={$key}={$val}";
            } else {
                $sentence[] = (string) $val;
            }
        }

        $this->writeSentence($sentence);

        return $this->readResponse();
    }

    public function ping(): bool
    {
        try {
            $result = $this->command('/system/identity/print');

            return ! empty($result);
        } catch (Exception) {
            return false;
        }
    }

    public function getSystemResource(): array
    {
        $response = $this->command('/system/resource/print');

        return $response[0] ?? [];
    }

    /**
     * @param  array<int, string>  $sentence
     */
    protected function writeSentence(array $sentence): void
    {
        foreach ($sentence as $word) {
            $this->writeWord($word);
        }
        $this->writeWord(''); // Sentence delimiter
    }

    protected function writeWord(string $word): void
    {
        $len = strlen($word);
        $header = $this->encodeLength($len);
        @fwrite($this->socket, $header.$word);
    }

    protected function encodeLength(int $length): string
    {
        if ($length < 0x80) {
            return chr($length);
        }
        if ($length < 0x4000) {
            $length |= 0x8000;

            return chr(($length >> 8) & 0xFF).chr($length & 0xFF);
        }
        if ($length < 0x200000) {
            $length |= 0xC00000;

            return chr(($length >> 16) & 0xFF).chr(($length >> 8) & 0xFF).chr($length & 0xFF);
        }
        if ($length < 0x10000000) {
            $length |= 0xE0000000;

            return chr(($length >> 24) & 0xFF).chr(($length >> 16) & 0xFF).chr(($length >> 8) & 0xFF).chr($length & 0xFF);
        }

        return chr(0xF0).chr(($length >> 24) & 0xFF).chr(($length >> 16) & 0xFF).chr(($length >> 8) & 0xFF).chr($length & 0xFF);
    }

    /**
     * @return array<int, array<string, string>>
     */
    protected function readResponse(): array
    {
        $items = [];
        $current = [];

        while (true) {
            $word = $this->readWord();

            if ($word === null) {
                break;
            }

            if ($word === '') {
                if (! empty($current)) {
                    $items[] = $current;
                    $current = [];
                }

                continue;
            }

            if ($word === '!done') {
                if (! empty($current)) {
                    $items[] = $current;
                }
                break;
            }

            if ($word === '!trap' || $word === '!fatal') {
                $current['!trap'] = 'true';

                continue;
            }

            if (str_starts_with($word, '=')) {
                $parts = explode('=', substr($word, 1), 2);
                $key = $parts[0];
                $val = $parts[1] ?? '';
                $current[$key] = $val;
            } elseif (str_starts_with($word, '!re')) {
                // Next record item
                if (! empty($current)) {
                    $items[] = $current;
                    $current = [];
                }
            }
        }

        return $items;
    }

    protected function readWord(): ?string
    {
        $len = $this->readLength();
        if ($len === null) {
            return null;
        }

        if ($len === 0) {
            return '';
        }

        $received = 0;
        $word = '';

        while ($received < $len) {
            if (! is_resource($this->socket) || feof($this->socket)) {
                $this->disconnect();
                throw new RouterConnectionException($this->host, $this->port, 'MikroTik socket connection terminated unexpectedly.');
            }

            $chunk = @fread($this->socket, $len - $received);
            if ($chunk === false || $chunk === '') {
                $meta = is_resource($this->socket) ? stream_get_meta_data($this->socket) : ['timed_out' => true];
                if ($meta['timed_out'] ?? false) {
                    $this->disconnect();
                    throw new RouterConnectionException($this->host, $this->port, 'MikroTik socket read timed out.');
                }
                if (! is_resource($this->socket) || feof($this->socket)) {
                    $this->disconnect();
                    throw new RouterConnectionException($this->host, $this->port, 'MikroTik socket reached EOF unexpectedly.');
                }
                break;
            }
            $word .= $chunk;
            $received += strlen($chunk);
        }

        return $word;
    }

    protected function readLength(): ?int
    {
        if (! is_resource($this->socket) || feof($this->socket)) {
            return null;
        }

        $char = @fread($this->socket, 1);
        if ($char === false || $char === '') {
            $meta = is_resource($this->socket) ? stream_get_meta_data($this->socket) : ['timed_out' => true];
            if ($meta['timed_out'] ?? false) {
                $this->disconnect();
                throw new RouterConnectionException($this->host, $this->port, 'MikroTik socket read length timed out.');
            }

            return null;
        }

        $byte = ord($char);

        if (($byte & 0x80) === 0x00) {
            return $byte;
        }

        if (($byte & 0xC0) === 0x80) {
            $byte2 = ord((string) fread($this->socket, 1));

            return (($byte & 0x3F) << 8) | $byte2;
        }

        if (($byte & 0xE0) === 0xC0) {
            $bytes = fread($this->socket, 2);
            if ($bytes === false || strlen($bytes) < 2) {
                return null;
            }

            return (($byte & 0x1F) << 16) | (ord($bytes[0]) << 8) | ord($bytes[1]);
        }

        if (($byte & 0xF0) === 0xE0) {
            $bytes = fread($this->socket, 3);
            if ($bytes === false || strlen($bytes) < 3) {
                return null;
            }

            return (($byte & 0x0F) << 24) | (ord($bytes[0]) << 16) | (ord($bytes[1]) << 8) | ord($bytes[2]);
        }

        if ($byte === 0xF0) {
            $bytes = fread($this->socket, 4);
            if ($bytes === false || strlen($bytes) < 4) {
                return null;
            }

            return (ord($bytes[0]) << 24) | (ord($bytes[1]) << 16) | (ord($bytes[2]) << 8) | ord($bytes[3]);
        }

        return null;
    }

    public function __destruct()
    {
        $this->disconnect();
    }
}
