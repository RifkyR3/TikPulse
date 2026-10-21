<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property bool $is_superadmin
 * @property bool $all_routers
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Group extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'is_superadmin',
        'all_routers',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_superadmin' => 'boolean',
            'all_routers' => 'boolean',
        ];
    }

    /**
     * Users belonging to this group.
     *
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Module permissions assigned to this group.
     *
     * @return HasMany<GroupModulePermission, $this>
     */
    public function permissions(): HasMany
    {
        return $this->hasMany(GroupModulePermission::class);
    }

    /**
     * Routers this group is permitted to access (when all_routers is false).
     *
     * @return BelongsToMany<Router, $this>
     */
    public function routers(): BelongsToMany
    {
        return $this->belongsToMany(Router::class, 'group_router');
    }

    /**
     * Check if this group has a specific permission on a module.
     */
    public function hasPermission(string $moduleKey, string $action = 'read'): bool
    {
        if ($this->is_superadmin) {
            return true;
        }

        /** @var GroupModulePermission|null $perm */
        $perm = $this->permissions->firstWhere('module_key', $moduleKey);
        if (! $perm) {
            return false;
        }

        return match ($action) {
            'read' => $perm->can_read,
            'write' => $perm->can_write,
            'update' => $perm->can_update,
            'delete' => $perm->can_delete,
            default => false,
        };
    }

    /**
     * Check if this group can access a specific router.
     */
    public function canAccessRouter(int $routerId): bool
    {
        if ($this->is_superadmin || $this->all_routers) {
            return true;
        }

        return $this->routers->contains('id', $routerId);
    }

    /**
     * Sync module permissions for this group.
     *
     * @param  array<string, array{can_read?: bool, can_write?: bool, can_update?: bool, can_delete?: bool}>  $permissionsMap
     */
    public function syncPermissions(array $permissionsMap): void
    {
        foreach ($permissionsMap as $moduleKey => $actions) {
            GroupModulePermission::updateOrCreate(
                [
                    'group_id' => $this->id,
                    'module_key' => $moduleKey,
                ],
                [
                    'can_read' => (bool) ($actions['can_read'] ?? false),
                    'can_write' => (bool) ($actions['can_write'] ?? false),
                    'can_update' => (bool) ($actions['can_update'] ?? false),
                    'can_delete' => (bool) ($actions['can_delete'] ?? false),
                ]
            );
        }
    }
}
