<?php

declare(strict_types=1);

namespace App\Models;

use App\Infrastructure\Kafka\KafkaService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $router_id
 * @property int|null $user_id
 * @property string $command_type
 * @property string $target_endpoint
 * @property array|null $payload
 * @property bool $is_blocked
 * @property string|null $bypass_reason
 * @property string $client_ip
 * @property Carbon|null $created_at
 */
class SafeModeAuditLog extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected static function booted(): void
    {
        static::created(function (self $log): void {
            if (app()->bound(KafkaService::class)) {
                app(KafkaService::class)->publishAudit(
                    $log->command_type,
                    $log->router_id,
                    $log->toArray()
                );
            }
        });
    }

    protected $fillable = [
        'router_id',
        'user_id',
        'command_type',
        'target_endpoint',
        'payload',
        'is_blocked',
        'bypass_reason',
        'client_ip',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'router_id' => 'integer',
            'user_id' => 'integer',
            'payload' => 'array',
            'is_blocked' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Router, $this>
     */
    public function router(): BelongsTo
    {
        return $this->belongsTo(Router::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
