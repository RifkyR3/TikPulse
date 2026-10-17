<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;

/**
 * @property int $id
 * @property string $name
 * @property string $host
 * @property int $api_port
 * @property string $protocol
 * @property string $username
 * @property string $password_encrypted
 * @property bool $is_safe_mode_enabled
 * @property bool $use_tls
 * @property int $timeout_seconds
 * @property string|null $description
 * @property string $status
 * @property Carbon|null $last_connected_at
 * @property string|null $ros_version
 * @property string|null $board_model
 * @property int|null $cpu_load
 * @property int|null $free_memory_bytes
 * @property int|null $total_memory_bytes
 * @property string|null $uptime
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Router extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'host',
        'api_port',
        'protocol',
        'username',
        'password_encrypted',
        'is_safe_mode_enabled',
        'use_tls',
        'timeout_seconds',
        'description',
        'status',
        'last_connected_at',
        'ros_version',
        'board_model',
        'cpu_load',
        'free_memory_bytes',
        'total_memory_bytes',
        'uptime',
    ];

    protected $hidden = [
        'password_encrypted',
    ];

    /**
     * @var list<string>
     */
    protected $appends = [
        'ip_address',
        'port',
        'free_memory',
        'total_memory',
        'memory_usage_percent',
    ];

    public function getIpAddressAttribute(): string
    {
        return $this->host;
    }

    public function getPortAttribute(): int
    {
        return $this->api_port;
    }

    public function getFreeMemoryAttribute(): ?int
    {
        return $this->free_memory_bytes;
    }

    public function getTotalMemoryAttribute(): ?int
    {
        return $this->total_memory_bytes;
    }

    public function getMemoryUsagePercentAttribute(): ?int
    {
        if ($this->total_memory_bytes && $this->total_memory_bytes > 0 && $this->free_memory_bytes !== null) {
            return (int) min(100, max(0, round((($this->total_memory_bytes - $this->free_memory_bytes) / $this->total_memory_bytes) * 100)));
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'api_port' => 'integer',
            'is_safe_mode_enabled' => 'boolean',
            'use_tls' => 'boolean',
            'timeout_seconds' => 'integer',
            'last_connected_at' => 'datetime',
            'cpu_load' => 'integer',
            'free_memory_bytes' => 'integer',
            'total_memory_bytes' => 'integer',
        ];
    }

    public function getDecryptedPassword(): string
    {
        return Crypt::decryptString($this->password_encrypted);
    }

    public function setPlainPassword(string $plainPassword): void
    {
        $this->password_encrypted = Crypt::encryptString($plainPassword);
    }

    /**
     * @return HasMany<HotspotProfile, $this>
     */
    public function hotspotProfiles(): HasMany
    {
        return $this->hasMany(HotspotProfile::class);
    }

    /**
     * @return HasMany<Voucher, $this>
     */
    public function vouchers(): HasMany
    {
        return $this->hasMany(Voucher::class);
    }

    /**
     * @return HasMany<VoucherBatch, $this>
     */
    public function voucherBatches(): HasMany
    {
        return $this->hasMany(VoucherBatch::class);
    }

    /**
     * @return HasMany<PppProfile, $this>
     */
    public function pppProfiles(): HasMany
    {
        return $this->hasMany(PppProfile::class);
    }

    /**
     * @return HasMany<DedicatedUser, $this>
     */
    public function dedicatedUsers(): HasMany
    {
        return $this->hasMany(DedicatedUser::class);
    }

    /**
     * @return HasMany<SafeModeAuditLog, $this>
     */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(SafeModeAuditLog::class);
    }
}
