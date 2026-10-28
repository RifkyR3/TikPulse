<?php

declare(strict_types=1);

namespace App\Infrastructure\Kafka;

use Illuminate\Support\Facades\Log;
use Throwable;

class KafkaService
{
    /**
     * Publish an event payload to a Kafka topic.
     *
     * @param  array<string, mixed>|string  $payload
     */
    public function publish(string $topic, array|string $payload, ?string $key = null): bool
    {
        if (! $this->isEnabled()) {
            return true;
        }

        try {
            $value = is_array($payload) ? (string) json_encode($payload, JSON_UNESCAPED_SLASHES) : $payload;
            $packet = $this->buildProducePacket($topic, $value, $key);

            return $this->sendPacket($packet);
        } catch (Throwable $e) {
            Log::warning('Kafka publish failed: '.$e->getMessage(), [
                'topic' => $topic,
                'key' => $key,
            ]);

            return false;
        }
    }

    /**
     * Publish live router telemetry to Kafka.
     *
     * @param  array<string, mixed>  $data
     */
    public function publishTelemetry(int|string $routerId, array $data): bool
    {
        $topic = (string) config('kafka.topics.telemetry', 'tikpulse.telemetry');

        return $this->publish($topic, $data, (string) $routerId);
    }

    /**
     * Publish safe mode audit log to Kafka.
     *
     * @param  array<string, mixed>  $data
     */
    public function publishAudit(string $commandType, int|string|null $routerId, array $data): bool
    {
        $topic = (string) config('kafka.topics.audit', 'tikpulse.safemode.audit');

        return $this->publish($topic, $data, $routerId !== null ? (string) $routerId : $commandType);
    }

    /**
     * Check if Kafka streaming is enabled in configuration.
     */
    public function isEnabled(): bool
    {
        return (bool) config('kafka.enabled', false);
    }

    /**
     * Test connection to configured Kafka broker.
     */
    public function testConnection(): bool
    {
        [$host, $port] = $this->resolveFirstBroker();
        $timeout = (float) (config('kafka.timeout_ms', 1000) / 1000);

        $stream = @stream_socket_client(
            "tcp://{$host}:{$port}",
            $errno,
            $errstr,
            $timeout,
            STREAM_CLIENT_CONNECT
        );

        if (! $stream) {
            return false;
        }

        fclose($stream);

        return true;
    }

    /**
     * Build binary Kafka ProduceRequest packet (API Key 0, Version 0, acks = 0 fire-and-forget).
     *
     * ponytail: pure PHP socket Produce v0 (acks=0); upgrade to ext-rdkafka if consumer groups or transactional batches needed.
     */
    public function buildProducePacket(string $topic, string $value, ?string $key = null): string
    {
        $keyBytes = $key !== null ? pack('Na*', strlen($key), $key) : pack('N', -1);
        $valBytes = pack('Na*', strlen($value), $value);

        // Message: magic(1) + attributes(1) + key + value
        $msgBody = pack('CC', 0, 0).$keyBytes.$valBytes;
        $crc = crc32($msgBody);
        $message = pack('N', $crc).$msgBody;

        // MessageSet: offset(int64 = 0) + message_size(int32) + message
        $messageSet = pack('NNN', 0, 0, strlen($message)).$message;

        // ProduceRequest body:
        // required_acks(int16 = 0) + timeout(int32 = 1000ms) + topic_count(int32 = 1)
        // topic_name(int16 len + str) + partition_count(int32 = 1)
        // partition(int32 = 0) + message_set_size(int32) + message_set
        $reqBody = pack('nNN', 0, (int) config('kafka.timeout_ms', 1000), 1)
            .pack('n', strlen($topic)).$topic
            .pack('NNN', 1, 0, strlen($messageSet))
            .$messageSet;

        // Request Header: api_key(int16 = 0) + api_version(int16 = 0) + correlation_id(int32 = 1) + client_id
        $clientId = (string) config('kafka.client_id', 'tikpulse');
        $header = pack('nnNn', 0, 0, 1, strlen($clientId)).$clientId;

        $payload = $header.$reqBody;

        // Length prefix (int32)
        return pack('N', strlen($payload)).$payload;
    }

    /**
     * Send raw binary packet to first available Kafka broker.
     */
    protected function sendPacket(string $packet): bool
    {
        [$host, $port] = $this->resolveFirstBroker();
        $timeout = (float) (config('kafka.timeout_ms', 1000) / 1000);

        $stream = @stream_socket_client(
            "tcp://{$host}:{$port}",
            $errno,
            $errstr,
            $timeout,
            STREAM_CLIENT_CONNECT
        );

        if (! $stream) {
            Log::debug("Kafka broker unreachable at {$host}:{$port}: {$errstr}");

            return false;
        }

        stream_set_timeout($stream, (int) $timeout, (int) (($timeout - (int) $timeout) * 1000000));
        @fwrite($stream, $packet);
        @fclose($stream);

        return true;
    }

    /**
     * Resolve host and port from comma-separated brokers config.
     *
     * @return array{0: string, 1: int}
     */
    protected function resolveFirstBroker(): array
    {
        $brokers = explode(',', (string) config('kafka.brokers', '127.0.0.1:9092'));
        $first = trim($brokers[0] ?? '127.0.0.1:9092');
        $parts = explode(':', $first);

        $host = $parts[0] !== '' ? $parts[0] : '127.0.0.1';
        $port = isset($parts[1]) ? (int) $parts[1] : 9092;

        return [$host, $port];
    }
}
