<?php

declare(strict_types=1);

namespace App\Models;

use App\Notifications\VerifyEmailCodeNotification;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string $role
 * @property int|null $group_id
 * @property bool $is_active
 * @property string|null $avatar_url
 * @property string $preferred_locale
 * @property array<string, mixed>|null $settings
 * @property int|null $simulated_group_id
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Group|null $group
 * @property-read Group|null $simulatedGroup
 */
#[Fillable([
    'name',
    'email',
    'password',
    'role',
    'group_id',
    'is_active',
    'avatar_url',
    'preferred_locale',
    'settings',
    'simulated_group_id',
])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'settings' => 'array',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /**
     * Permanent user group.
     *
     * @return BelongsTo<Group, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    /**
     * Simulated group for superadmin role testing.
     *
     * @return BelongsTo<Group, $this>
     */
    public function simulatedGroup(): BelongsTo
    {
        return $this->belongsTo(Group::class, 'simulated_group_id');
    }

    /**
     * Check if user is inherently a superadmin.
     */
    public function isSuperAdmin(): bool
    {
        if ($this->role === 'superadmin') {
            return true;
        }

        return (bool) ($this->group?->is_superadmin ?? false);
    }

    /**
     * Check if the user is currently simulating another role.
     */
    public function isSimulating(): bool
    {
        return $this->isSuperAdmin() && $this->simulated_group_id !== null;
    }

    /**
     * Get effective group (taking active simulation into account).
     */
    public function getEffectiveGroup(): ?Group
    {
        if ($this->isSimulating()) {
            return Group::with(['permissions', 'routers'])->find($this->simulated_group_id) ?? $this->group;
        }

        return $this->group;
    }

    /**
     * Check if the user is effectively superadmin right now.
     */
    public function isEffectiveSuperAdmin(): bool
    {
        if ($this->isSimulating()) {
            return (bool) ($this->getEffectiveGroup()?->is_superadmin ?? false);
        }

        return $this->isSuperAdmin();
    }

    /**
     * Check permission on a module.
     */
    public function hasModulePermission(string $moduleKey, string $action = 'read'): bool
    {
        if ($this->isEffectiveSuperAdmin()) {
            return true;
        }

        $group = $this->getEffectiveGroup();
        if (! $group) {
            // Fallback for role-based legacy accounts or factories without group
            if ($this->role === 'superadmin') {
                return true;
            }
            if ($this->role === 'admin') {
                return true;
            }
            if ($this->role === 'operator' || empty($this->role)) {
                return in_array($moduleKey, ['dashboard', 'routers', 'hotspot_vouchers', 'ppp_subscribers', 'safe_mode_logs'], true)
                    && in_array($action, ['read', 'write', 'update'], true);
            }

            return false;
        }

        return $group->hasPermission($moduleKey, $action);
    }

    /**
     * Check if the user can access a specific MikroTik router.
     */
    public function canAccessRouter(int $routerId): bool
    {
        if ($this->isEffectiveSuperAdmin()) {
            return true;
        }

        $group = $this->getEffectiveGroup();
        if (! $group) {
            return true; // legacy unrestricted
        }

        return $group->canAccessRouter($routerId);
    }

    /**
     * Check if user can access all routers without restriction.
     */
    public function canAccessAllRouters(): bool
    {
        if ($this->isEffectiveSuperAdmin()) {
            return true;
        }

        $group = $this->getEffectiveGroup();
        if (! $group) {
            return true;
        }

        return (bool) $group->all_routers;
    }

    /**
     * Get list of allowed router IDs. Returns null if unrestricted.
     *
     * @return list<int>|null
     */
    public function getAllowedRouterIds(): ?array
    {
        if ($this->canAccessAllRouters()) {
            return null;
        }

        $group = $this->getEffectiveGroup();
        if (! $group) {
            return [];
        }

        /** @var list<int> $ids */
        $ids = $group->routers()->pluck('routers.id')->all();

        return $ids;
    }

    /**
     * Get map of effective permissions for all modules.
     *
     * @return array<string, array{can_read: bool, can_write: bool, can_update: bool, can_delete: bool}>
     */
    public function getEffectivePermissions(): array
    {
        $modules = Module::all();
        $result = [];

        $isSuper = $this->isEffectiveSuperAdmin();
        $group = $this->getEffectiveGroup();
        $groupPermissions = $group ? $group->permissions()->get() : collect();

        foreach ($modules as $mod) {
            if ($isSuper) {
                $result[$mod->key] = [
                    'can_read' => true,
                    'can_write' => true,
                    'can_update' => true,
                    'can_delete' => true,
                ];

                continue;
            }

            /** @var GroupModulePermission|null $perm */
            $perm = $groupPermissions->firstWhere('module_key', $mod->key);
            $result[$mod->key] = [
                'can_read' => (bool) ($perm?->can_read ?? false),
                'can_write' => (bool) ($perm?->can_write ?? false),
                'can_update' => (bool) ($perm?->can_update ?? false),
                'can_delete' => (bool) ($perm?->can_delete ?? false),
            ];
        }

        return $result;
    }

    /**
     * Get setting value with default.
     */
    public function getSetting(string $key, mixed $default = null): mixed
    {
        $settings = $this->settings ?? [];

        return $settings[$key] ?? $default;
    }

    /**
     * Send the email verification notification containing a 6-digit verification code.
     */
    public function sendEmailVerificationNotification(): void
    {
        $code = (string) random_int(100000, 999999);

        Cache::put("email_verify_code_{$this->id}", [
            'code' => $code,
            'email' => $this->email,
        ], now()->addMinutes(15));

        $this->notify(new VerifyEmailCodeNotification($code));
    }
}
