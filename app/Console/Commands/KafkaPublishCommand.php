<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Infrastructure\Kafka\KafkaService;
use Illuminate\Console\Command;

class KafkaPublishCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'kafka:publish 
                            {topic : Target Kafka topic name}
                            {message : Message payload or JSON string}
                            {--key= : Optional message key}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Publish a message to an Apache Kafka topic';

    /**
     * Execute the console command.
     */
    public function handle(KafkaService $kafka): int
    {
        $topic = (string) $this->argument('topic');
        $rawMessage = (string) $this->argument('message');
        $key = $this->option('key') !== null ? (string) $this->option('key') : null;

        $payload = json_validate($rawMessage)
            ? (array) json_decode($rawMessage, true)
            : $rawMessage;

        $this->info("Publishing to Kafka topic: {$topic}...");

        $success = $kafka->publish($topic, $payload, $key);

        if ($success) {
            $this->info('Message published successfully.');

            return self::SUCCESS;
        }

        $this->warn('Kafka publish returned false. Broker may be offline or Kafka is disabled.');

        return self::FAILURE;
    }
}
