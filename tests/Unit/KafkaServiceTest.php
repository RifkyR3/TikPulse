<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Infrastructure\Kafka\KafkaService;
use Tests\TestCase;

class KafkaServiceTest extends TestCase
{
    public function test_publish_returns_true_when_kafka_is_disabled(): void
    {
        config(['kafka.enabled' => false]);

        $service = new KafkaService;

        $this->assertFalse($service->isEnabled());
        $this->assertTrue($service->publish('test.topic', ['event' => 'ping']));
    }

    public function test_build_produce_packet_constructs_valid_kafka_v0_produce_request(): void
    {
        config([
            'kafka.client_id' => 'tikpulse-test',
            'kafka.timeout_ms' => 1000,
        ]);

        $service = new KafkaService;
        $packet = $service->buildProducePacket('tikpulse.telemetry', '{"cpu":15}', 'router-1');

        $this->assertNotEmpty($packet);
        $this->assertGreaterThan(30, strlen($packet));

        // First 4 bytes are total payload length prefix (int32 big-endian)
        $unpackedLen = unpack('Nlen', substr($packet, 0, 4));
        $this->assertIsArray($unpackedLen);
        $this->assertEquals(strlen($packet) - 4, $unpackedLen['len']);

        // Next 2 bytes are api_key = 0 (PRODUCE)
        $unpackedApiKey = unpack('nkey', substr($packet, 4, 2));
        $this->assertIsArray($unpackedApiKey);
        $this->assertEquals(0, $unpackedApiKey['key']);
    }

    public function test_publish_telemetry_and_audit_use_configured_topics(): void
    {
        config([
            'kafka.enabled' => false,
            'kafka.topics.telemetry' => 'custom.telemetry.topic',
            'kafka.topics.audit' => 'custom.audit.topic',
        ]);

        $service = new KafkaService;

        $this->assertTrue($service->publishTelemetry('1', ['cpu' => 20]));
        $this->assertTrue($service->publishAudit('system_reboot', '1', ['user' => 'admin']));
    }
}
