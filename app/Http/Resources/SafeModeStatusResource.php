<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Router;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Router
 */
class SafeModeStatusResource extends JsonResource
{
    public function __construct(
        $resource,
        protected bool $isBypassed = false,
        protected ?int $remainingSeconds = null
    ) {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'router_id' => $this->id,
            'router_name' => $this->name,
            'is_safe_mode_enabled' => $this->is_safe_mode_enabled,
            'is_bypassed' => $this->isBypassed,
            'remaining_bypass_seconds' => $this->remainingSeconds,
            'effective_state' => (! $this->is_safe_mode_enabled || $this->isBypassed) ? 'UNLOCKED' : 'PROTECTED',
            'badge_color' => (! $this->is_safe_mode_enabled || $this->isBypassed) ? 'amber' : 'emerald',
            'status_label' => (! $this->is_safe_mode_enabled || $this->isBypassed) ? 'SAFE MODE UNLOCKED' : 'SAFE MODE ACTIVE',
        ];
    }
}
