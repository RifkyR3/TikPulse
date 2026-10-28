<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Kafka Event Streaming Configuration
    |--------------------------------------------------------------------------
    |
    | Distributed event streaming for live router telemetry ingestion and
    | safe mode audit logging. Uses lightweight pure PHP socket produce protocol.
    |
    */

    'enabled' => (bool) env('KAFKA_ENABLED', false),

    'brokers' => env('KAFKA_BROKERS', '127.0.0.1:9092'),

    'client_id' => env('KAFKA_CLIENT_ID', 'tikpulse'),

    'timeout_ms' => (int) env('KAFKA_TIMEOUT_MS', 1000),

    'topics' => [
        'telemetry' => env('KAFKA_TOPIC_TELEMETRY', 'tikpulse.telemetry'),
        'audit' => env('KAFKA_TOPIC_AUDIT', 'tikpulse.safemode.audit'),
        'events' => env('KAFKA_TOPIC_EVENTS', 'tikpulse.events'),
    ],
];
