<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Router;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ApiAuthAndSafeModeTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_and_receive_sanctum_token(): void
    {
        $user = User::factory()->create([
            'email' => 'admin@tikpulse.test',
            'password' => Hash::make('secret123'),
            'role' => 'superadmin',
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@tikpulse.test',
            'password' => 'secret123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'user' => ['id', 'name', 'email', 'role', 'is_active'],
                    'token',
                    'token_type',
                ],
            ]);

        $token = $response->json('data.token');
        $this->assertNotEmpty($token);

        // Verify token grants access to protected /me endpoint
        $meResponse = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/auth/me');

        $meResponse->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'email' => 'admin@tikpulse.test',
                    ],
                ],
            ]);
    }

    public function test_login_fails_with_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'operator@tikpulse.test',
            'password' => Hash::make('correct-password'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'operator@tikpulse.test',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_safe_mode_blocks_destructive_reboot_command_by_default(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('password123'),
        ]);

        $router = new Router([
            'name' => 'Main Gateway MikroTik',
            'host' => '192.168.88.1',
            'api_port' => 8728,
            'protocol' => 'socket',
            'username' => 'admin',
            'is_safe_mode_enabled' => true,
            'timeout_seconds' => 5,
        ]);
        $router->setPlainPassword('mikrotikpass');
        $router->save();

        // Attempt reboot without bypass token
        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/routers/{$router->id}/reboot");

        $response->assertStatus(403)
            ->assertJson([
                'status' => 'error',
                'code' => 'SAFE_MODE_ACTIVE',
                'error_details' => [
                    'router_id' => $router->id,
                    'blocked_action' => 'system_reboot',
                ],
            ]);

        // Verify audit log was recorded
        $this->assertDatabaseHas('safe_mode_audit_logs', [
            'router_id' => $router->id,
            'command_type' => 'system_reboot',
            'is_blocked' => true,
        ]);
    }

    public function test_two_step_unlock_issues_valid_temporary_bypass_token(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('adminpass123'),
        ]);

        $router = new Router([
            'name' => 'Hotspot Core Router',
            'host' => '10.10.10.1',
            'api_port' => 8728,
            'protocol' => 'socket',
            'username' => 'api_user',
            'is_safe_mode_enabled' => true,
        ]);
        $router->setPlainPassword('routerpass');
        $router->save();

        // 2-Step Unlock request
        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/routers/{$router->id}/safe-mode/unlock", [
                'password' => 'adminpass123',
                'verification_text' => 'UNLOCK',
                'reason' => 'Emergency maintenance',
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'router_id',
                    'bypass_token',
                    'expires_in_seconds',
                    'expires_at',
                ],
            ]);

        $bypassToken = $response->json('data.bypass_token');
        $this->assertNotEmpty($bypassToken);

        // Check Safe Mode status with bypass token
        $statusResponse = $this->actingAs($user, 'sanctum')
            ->withHeader('X-SafeMode-Bypass', $bypassToken)
            ->getJson("/api/v1/routers/{$router->id}/safe-mode/status");

        $statusResponse->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'router_id' => $router->id,
                    'is_safe_mode_enabled' => true,
                    'is_bypassed' => true,
                    'effective_state' => 'UNLOCKED',
                ],
            ]);
    }

    public function test_user_locale_defaults_to_en(): void
    {
        $user = User::factory()->create([
            'email' => 'defaultlang@tikpulse.test',
            'preferred_locale' => 'en',
        ]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/auth/me');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'preferred_locale' => 'en',
                    ],
                ],
            ]);
    }

    public function test_user_can_update_preferred_locale_to_id(): void
    {
        $user = User::factory()->create([
            'preferred_locale' => 'en',
        ]);

        $updateResponse = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/auth/locale', [
                'locale' => 'id',
            ]);

        $updateResponse->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'preferred_locale' => 'id',
                ],
            ]);

        $this->assertSame('id', $user->fresh()->preferred_locale);

        // Fetching /me reflects the updated locale
        $meResponse = $this->actingAs($user, 'sanctum')->getJson('/api/v1/auth/me');
        $meResponse->assertStatus(200)
            ->assertJson([
                'data' => [
                    'user' => [
                        'preferred_locale' => 'id',
                    ],
                ],
            ]);
    }

    public function test_update_locale_rejects_unsupported_language(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/auth/locale', [
                'locale' => 'fr',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['locale']);
    }

    public function test_router_resource_includes_ip_address_alias_and_memory_fields(): void
    {
        $user = User::factory()->create();

        $router = new Router([
            'name' => 'HEX - Main Gateway',
            'host' => '10.0.1.1',
            'api_port' => 8728,
            'protocol' => 'socket',
            'username' => 'admin',
            'is_safe_mode_enabled' => true,
            'cpu_load' => 5,
            'free_memory_bytes' => 300000000,
            'total_memory_bytes' => 512000000,
            'uptime' => '2d16h2m36s',
        ]);
        $router->setPlainPassword('secret');
        $router->save();

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/routers');

        $response->assertStatus(200)
            ->assertJsonFragment([
                'id' => $router->id,
                'name' => 'HEX - Main Gateway',
                'host' => '10.0.1.1',
                'ip_address' => '10.0.1.1',
                'api_port' => 8728,
                'port' => 8728,
                'cpu_load' => 5,
                'uptime' => '2d16h2m36s',
            ]);
    }
}
