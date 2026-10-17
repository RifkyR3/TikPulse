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
 * @property string|null $local_address
 * @property string|null $remote_address
 * @property string|null $rate_limit
 * @property bool $only_one
 * @property string|null $dns_server
 * @property float $price
 * @property string|null $comment
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class PppProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'router_id',
        'name',
        'local_address',
        'remote_address',
        'rate_limit',
        'only_one',
        'dns_server',
        'price',
        'comment',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'router_id' => 'integer',
            'only_one' => 'boolean',
            'price' => 'decimal:2',
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
     * @return HasMany<DedicatedUser, $this>
     */
    public function dedicatedUsers(): HasMany
    {
        return $this->hasMany(DedicatedUser::class, 'ppp_profile_id');
    }
}
