<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string|null $batch_id
 * @property int $router_id
 * @property int $profile_id
 * @property string $username
 * @property string $password
 * @property string|null $time_limit
 * @property int|null $data_limit_bytes
 * @property string|null $comment
 * @property string $status
 * @property bool $is_printed
 * @property Carbon|null $printed_at
 * @property Carbon|null $first_login_at
 * @property Carbon|null $expires_at
 * @property string|null $mac_address
 * @property string|null $ip_address
 * @property bool $synced_to_router
 * @property string|null $sync_failed_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Voucher extends Model
{
    use HasFactory, HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'batch_id',
        'router_id',
        'profile_id',
        'username',
        'password',
        'time_limit',
        'data_limit_bytes',
        'comment',
        'status',
        'is_printed',
        'printed_at',
        'first_login_at',
        'expires_at',
        'mac_address',
        'ip_address',
        'synced_to_router',
        'sync_failed_reason',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'router_id' => 'integer',
            'profile_id' => 'integer',
            'data_limit_bytes' => 'integer',
            'is_printed' => 'boolean',
            'synced_to_router' => 'boolean',
            'printed_at' => 'datetime',
            'first_login_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<VoucherBatch, $this>
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(VoucherBatch::class, 'batch_id');
    }

    /**
     * @return BelongsTo<Router, $this>
     */
    public function router(): BelongsTo
    {
        return $this->belongsTo(Router::class);
    }

    /**
     * @return BelongsTo<HotspotProfile, $this>
     */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(HotspotProfile::class, 'profile_id');
    }
}
