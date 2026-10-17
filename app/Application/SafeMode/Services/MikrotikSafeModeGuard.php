<?php

declare(strict_types=1);

namespace App\Application\SafeMode\Services;

use App\Domain\SafeMode\Enums\DestructiveAction;
use App\Domain\SafeMode\Exceptions\SafeModeActiveException;
use App\Models\Router;
use App\Models\SafeModeAuditLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class MikrotikSafeModeGuard
{
    /**
     * Cache key prefix for temporary Safe Mode bypass tokens.
     */
    protected const CACHE_PREFIX = 'safemode_bypass:';

    /**
     * Check if a MikroTik command path is classified as destructive.
     *
     * @param  array<string, mixed>  $payload
     */
    public function isDestructiveCommand(string $command, array $payload = []): ?DestructiveAction
    {
        $normalized = strtolower(trim($command));

        if (str_contains($normalized, '/system/reboot')) {
            return DestructiveAction::SystemReboot;
        }

        if (str_contains($normalized, '/system/shutdown')) {
            return DestructiveAction::SystemShutdown;
        }

        if (str_contains($normalized, '/system/reset-configuration')) {
            return DestructiveAction::ResetConfiguration;
        }

        if (str_contains($normalized, '/system/package/update') || str_contains($normalized, '/system/routerboard/upgrade')) {
            return DestructiveAction::PackageUpgrade;
        }

        if (str_contains($normalized, '/ip/firewall') && (str_contains($normalized, 'remove') || str_contains($normalized, 'disable'))) {
            return DestructiveAction::FirewallModification;
        }

        if (str_contains($normalized, '/ip/hotspot/user/remove')) {
            return DestructiveAction::BulkDeleteVouchers;
        }

        if (str_contains($normalized, '/ip/hotspot/user/profile/remove')) {
            return DestructiveAction::BulkDeleteProfiles;
        }

        if (str_contains($normalized, '/ppp/secret/remove')) {
            return DestructiveAction::BulkDeletePppSecrets;
        }

        if (str_contains($normalized, '/ppp/profile/remove')) {
            return DestructiveAction::BulkDeletePppProfiles;
        }

        if (str_contains($normalized, '/system/script') && str_contains($normalized, 'run')) {
            return DestructiveAction::ScriptExecution;
        }

        return null;
    }

    /**
     * Issue a temporary 2-step bypass token for 15 minutes.
     */
    public function createBypassToken(Router $router, int $userId, int $durationMinutes = 15): string
    {
        $token = 'smb_'.Str::random(40);
        $cacheKey = self::CACHE_PREFIX.$router->id.':'.$token;

        Cache::put($cacheKey, [
            'router_id' => $router->id,
            'user_id' => $userId,
            'created_at' => Carbon::now()->toIso8601String(),
        ], Carbon::now()->addMinutes($durationMinutes));

        return $token;
    }

    /**
     * Check whether the provided bypass token is valid and unexpired.
     */
    public function isBypassTokenValid(Router $router, ?string $token): bool
    {
        if (empty($token)) {
            return false;
        }

        $cacheKey = self::CACHE_PREFIX.$router->id.':'.$token;

        return Cache::has($cacheKey);
    }

    /**
     * Invalidate bypass token immediately (lock router again).
     */
    public function revokeBypassToken(Router $router, string $token): void
    {
        $cacheKey = self::CACHE_PREFIX.$router->id.':'.$token;
        Cache::forget($cacheKey);
    }

    /**
     * Enforce guardrail policy: throws SafeModeActiveException if blocked.
     *
     * @param  array<string, mixed>  $payload
     *
     * @throws SafeModeActiveException
     */
    public function enforce(
        Router $router,
        DestructiveAction $action,
        string $endpoint,
        array $payload = [],
        ?string $bypassToken = null,
        ?string $bypassReason = null
    ): void {
        $userId = Auth::id();
        $clientIp = request()->ip() ?? '127.0.0.1';

        // If Safe Mode is enabled on the router and bypass is not valid
        $isBypassed = $this->isBypassTokenValid($router, $bypassToken);

        if ($router->is_safe_mode_enabled && ! $isBypassed) {
            // Log the blocked attempt
            SafeModeAuditLog::create([
                'router_id' => $router->id,
                'user_id' => $userId,
                'command_type' => $action->value,
                'target_endpoint' => $endpoint,
                'payload' => $payload,
                'is_blocked' => true,
                'bypass_reason' => null,
                'client_ip' => $clientIp,
                'created_at' => Carbon::now(),
            ]);

            throw new SafeModeActiveException($router->id, $action, $endpoint);
        }

        // Record allowed execution if bypass was utilized
        if ($isBypassed) {
            SafeModeAuditLog::create([
                'router_id' => $router->id,
                'user_id' => $userId,
                'command_type' => $action->value,
                'target_endpoint' => $endpoint,
                'payload' => $payload,
                'is_blocked' => false,
                'bypass_reason' => $bypassReason ?? 'Authorized via 2-Step Safe Mode bypass token.',
                'client_ip' => $clientIp,
                'created_at' => Carbon::now(),
            ]);
        }
    }
}
