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
 * @property int $router_id
 * @property int|null $ppp_profile_id
 * @property string $name
 * @property string $password
 * @property string $service
 * @property string $profile
 * @property string|null $local_address
 * @property string|null $remote_address
 * @property string|null $caller_id
 * @property string|null $routes
 * @property string|null $customer_name
 * @property string|null $customer_phone
 * @property string|null $customer_address
 * @property float $monthly_price
 * @property string $billing_cycle
 * @property string|null $comment
 * @property bool $disabled
 * @property bool $synced_to_router
 * @property string|null $sync_failed_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class DedicatedUser extends Model
{
    use HasFactory, HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'router_id',
        'ppp_profile_id',
        'name',
        'password',
        'service',
        'profile',
        'local_address',
        'remote_address',
        'caller_id',
        'routes',
        'customer_name',
        'customer_phone',
        'customer_address',
        'monthly_price',
        'billing_cycle',
        'comment',
        'disabled',
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
            'ppp_profile_id' => 'integer',
            'monthly_price' => 'decimal:2',
            'disabled' => 'boolean',
            'synced_to_router' => 'boolean',
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
     * @return BelongsTo<PppProfile, $this>
     */
    public function pppProfile(): BelongsTo
    {
        return $this->belongsTo(PppProfile::class, 'ppp_profile_id');
    }
}
