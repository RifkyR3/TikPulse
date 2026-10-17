<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $router_id
 * @property string $name
 * @property int $shared_users
 * @property string|null $rate_limit
 * @property string|null $validity_duration
 * @property string|null $validity_unit
 * @property float $price
 * @property float $selling_price
 * @property string|null $on_login_script
 * @property string|null $on_logout_script
 * @property string|null $address_pool
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class HotspotProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'router_id',
        'name',
        'shared_users',
        'rate_limit',
        'validity_duration',
        'validity_unit',
        'price',
        'selling_price',
        'on_login_script',
        'on_logout_script',
        'address_pool',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'router_id' => 'integer',
            'shared_users' => 'integer',
            'price' => 'decimal:2',
            'selling_price' => 'decimal:2',
            'is_active' => 'boolean',
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
     * @return HasMany<Voucher, $this>
     */
    public function vouchers(): HasMany
    {
        return $this->hasMany(Voucher::class, 'profile_id');
    }

    /**
     * @return HasMany<VoucherBatch, $this>
     */
    public function voucherBatches(): HasMany
    {
        return $this->hasMany(VoucherBatch::class, 'profile_id');
    }
}
