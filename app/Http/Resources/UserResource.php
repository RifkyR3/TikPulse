<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $group = $this->relationLoaded('group') ? $this->group : $this->group()->first();
        $simulatedGroup = $this->isSimulating() ? $this->getEffectiveGroup() : null;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role,
            'group_id' => $this->group_id,
            'group' => $group ? [
                'id' => $group->id,
                'name' => $group->name,
                'slug' => $group->slug,
                'is_superadmin' => (bool) $group->is_superadmin,
                'all_routers' => (bool) $group->all_routers,
            ] : null,
            'is_active' => (bool) $this->is_active,
            'is_email_verified' => $this->hasVerifiedEmail(),
            'email_verified_at' => $this->email_verified_at?->toIso8601String(),
            'avatar_url' => $this->avatar_url,
            'preferred_locale' => $this->preferred_locale ?? 'en',
            'settings' => $this->settings ?? [
                'theme' => 'system',
                'accent_color' => 'sky',
                'refresh_interval' => 15,
                'table_compact_mode' => false,
            ],
            'is_superadmin' => $this->isSuperAdmin(),
            'can_switch_role' => $this->isSuperAdmin(),
            'is_simulating' => $this->isSimulating(),
            'simulated_group_id' => $this->simulated_group_id,
            'simulated_group' => $simulatedGroup ? [
                'id' => $simulatedGroup->id,
                'name' => $simulatedGroup->name,
                'slug' => $simulatedGroup->slug,
                'is_superadmin' => (bool) $simulatedGroup->is_superadmin,
                'all_routers' => (bool) $simulatedGroup->all_routers,
            ] : null,
            'effective_permissions' => $this->getEffectivePermissions(),
            'allowed_router_ids' => $this->getAllowedRouterIds(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
