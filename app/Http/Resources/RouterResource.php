<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Router;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Router
 */
class RouterResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'host' => $this->host,
            'api_port' => $this->api_port,
            'ip_address' => $this->host,
            'port' => $this->api_port,
            'protocol' => $this->protocol,
            'username' => $this->username,
            'is_safe_mode_enabled' => $this->is_safe_mode_enabled,
            'use_tls' => $this->use_tls,
            'timeout_seconds' => $this->timeout_seconds,
            'description' => $this->description,
            'status' => $this->status,
            'last_connected_at' => $this->last_connected_at?->toIso8601String(),
            'ros_version' => $this->ros_version,
            'board_model' => $this->board_model,
            'board_name' => $this->board_model,
            'cpu_load' => $this->cpu_load,
            'free_memory_bytes' => $this->free_memory_bytes,
            'total_memory_bytes' => $this->total_memory_bytes,
            'free_memory' => $this->free_memory_bytes,
            'total_memory' => $this->total_memory_bytes,
            'memory_usage_percent' => ($this->total_memory_bytes && $this->total_memory_bytes > 0 && $this->free_memory_bytes !== null)
                ? (int) min(100, max(0, round((($this->total_memory_bytes - $this->free_memory_bytes) / $this->total_memory_bytes) * 100)))
                : null,
            'uptime' => $this->uptime,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
