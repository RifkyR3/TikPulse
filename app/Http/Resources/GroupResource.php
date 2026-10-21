<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Group;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Group
 */
class GroupResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $permissions = $this->relationLoaded('permissions')
            ? $this->permissions->map(fn ($p) => [
                'module_key' => $p->module_key,
                'can_read' => $p->can_read,
                'can_write' => $p->can_write,
                'can_update' => $p->can_update,
                'can_delete' => $p->can_delete,
            ])
            : $this->permissions()->get()->map(fn ($p) => [
                'module_key' => $p->module_key,
                'can_read' => $p->can_read,
                'can_write' => $p->can_write,
                'can_update' => $p->can_update,
                'can_delete' => $p->can_delete,
            ]);

        $routerIds = $this->relationLoaded('routers')
            ? $this->routers->pluck('id')->all()
            : $this->routers()->pluck('routers.id')->all();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'is_superadmin' => (bool) $this->is_superadmin,
            'all_routers' => (bool) $this->all_routers,
            'permissions' => $permissions,
            'router_ids' => $routerIds,
            'users_count' => $this->users_count ?? $this->users()->count(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
