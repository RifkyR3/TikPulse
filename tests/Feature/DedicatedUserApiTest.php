<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\SafeMode\Services\MikrotikSafeModeGuard;
use App\Models\DedicatedUser;
use App\Models\PppProfile;
use App\Models\Router;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DedicatedUserApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Router $router;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'email' => 'admin@tikpulse.test',
            'role' => 'superadmin',
            'is_active' => true,
        ]);

        $this->router = new Router([
            'name' => 'Core-Gateway-PPP',
            'host' => '192.168.88.1',
            'api_port' => 8728,
            'protocol' => 'socket',
            'username' => 'admin',
            'is_safe_mode_enabled' => true,
            'timeout_seconds' => 1,
            'status' => 'online',
        ]);
        $this->router->setPlainPassword('secret123');
        $this->router->save();
    }

    public function test_can_create_and_list_ppp_profiles(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/routers/{$this->router->id}/ppp/profiles", [
                'name' => '10Mbps-Dedicated',
                'local_address' => '10.10.10.1',
                'remote_address' => 'pool-dedicated',
                'rate_limit' => '10M/10M',
                'only_one' => true,
                'dns_server' => '8.8.8.8,1.1.1.1',
                'price' => 350000,
                'comment' => 'Dedicated Office Package',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.name', '10Mbps-Dedicated')
            ->assertJsonPath('data.rate_limit', '10M/10M');

        $this->assertDatabaseHas('ppp_profiles', [
            'router_id' => $this->router->id,
            'name' => '10Mbps-Dedicated',
            'rate_limit' => '10M/10M',
        ]);

        $list = $this->actingAs($this->user)
            ->getJson("/api/v1/routers/{$this->router->id}/ppp/profiles");

        $list->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonCount(1, 'data');
    }

    public function test_can_update_ppp_profile(): void
    {
        $profile = PppProfile::create([
            'router_id' => $this->router->id,
            'name' => '20Mbps-Dedicated',
            'rate_limit' => '20M/20M',
            'price' => 500000,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user)
            ->putJson("/api/v1/routers/{$this->router->id}/ppp/profiles/{$profile->id}", [
                'rate_limit' => '25M/25M',
                'price' => 550000,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.rate_limit', '25M/25M');

        $this->assertDatabaseHas('ppp_profiles', [
            'id' => $profile->id,
            'rate_limit' => '25M/25M',
            'price' => 550000.00,
        ]);
    }

    public function test_safe_mode_blocks_ppp_profile_deletion_without_bypass(): void
    {
        $profile = PppProfile::create([
            'router_id' => $this->router->id,
            'name' => 'Profile-To-Delete',
            'rate_limit' => '10M/10M',
            'is_active' => true,
        ]);

        // Attempt deletion with Safe Mode active
        $response = $this->actingAs($this->user)
            ->deleteJson("/api/v1/routers/{$this->router->id}/ppp/profiles/{$profile->id}");

        $response->assertStatus(403)
            ->assertJsonPath('code', 'SAFE_MODE_ACTIVE');

        $this->assertDatabaseHas('ppp_profiles', [
            'id' => $profile->id,
        ]);
    }

    public function test_can_create_and_list_dedicated_users(): void
    {
        $profile = PppProfile::create([
            'router_id' => $this->router->id,
            'name' => 'Office-50M',
            'rate_limit' => '50M/50M',
            'price' => 1200000,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/routers/{$this->router->id}/ppp/secrets", [
                'name' => 'cust_pt_majujaya',
                'password' => 'secretPppoePass!1',
                'service' => 'pppoe',
                'profile' => 'Office-50M',
                'remote_address' => '10.10.10.100',
                'caller_id' => '00:11:22:33:44:55',
                'customer_name' => 'PT Maju Jaya Bersama',
                'customer_phone' => '081298765432',
                'customer_address' => 'Jl. Sudirman Tower A Lt. 10',
                'monthly_price' => 1200000,
                'comment' => 'Dedicated Fiber 50Mbps',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.name', 'cust_pt_majujaya')
            ->assertJsonPath('data.customer_name', 'PT Maju Jaya Bersama');

        $this->assertDatabaseHas('dedicated_users', [
            'router_id' => $this->router->id,
            'name' => 'cust_pt_majujaya',
            'customer_name' => 'PT Maju Jaya Bersama',
            'remote_address' => '10.10.10.100',
        ]);

        // List dedicated users
        $list = $this->actingAs($this->user)
            ->getJson("/api/v1/routers/{$this->router->id}/ppp/secrets");

        $list->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('meta.total_monthly_revenue', 1200000);
    }

    public function test_can_update_and_toggle_dedicated_user(): void
    {
        $user = DedicatedUser::create([
            'router_id' => $this->router->id,
            'name' => 'cust_toggle_test',
            'password' => 'pass123',
            'service' => 'pppoe',
            'profile' => 'default',
            'remote_address' => '10.10.10.101',
            'customer_name' => 'Bpk. Hendra',
            'monthly_price' => 300000,
            'disabled' => false,
            'synced_to_router' => true,
        ]);

        // Toggle user status to disabled
        $toggleRes = $this->actingAs($this->user)
            ->postJson("/api/v1/routers/{$this->router->id}/ppp/secrets/{$user->id}/toggle");

        $toggleRes->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.disabled', true);

        $this->assertDatabaseHas('dedicated_users', [
            'id' => $user->id,
            'disabled' => true,
        ]);

        // Update dedicated user details
        $updateRes = $this->actingAs($this->user)
            ->putJson("/api/v1/routers/{$this->router->id}/ppp/secrets/{$user->id}", [
                'customer_phone' => '085512345678',
                'monthly_price' => 350000,
            ]);

        $updateRes->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.customer_phone', '085512345678');

        $this->assertDatabaseHas('dedicated_users', [
            'id' => $user->id,
            'customer_phone' => '085512345678',
            'monthly_price' => 350000.00,
        ]);
    }

    public function test_safe_mode_blocks_dedicated_user_deletion_without_bypass(): void
    {
        $user = DedicatedUser::create([
            'router_id' => $this->router->id,
            'name' => 'cust_safe_test',
            'password' => 'pass123',
            'service' => 'pppoe',
            'profile' => 'default',
            'disabled' => false,
        ]);

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/v1/routers/{$this->router->id}/ppp/secrets/{$user->id}");

        $response->assertStatus(403)
            ->assertJsonPath('code', 'SAFE_MODE_ACTIVE');

        $this->assertDatabaseHas('dedicated_users', [
            'id' => $user->id,
        ]);
    }

    public function test_can_delete_dedicated_user_with_safe_mode_bypass(): void
    {
        $user = DedicatedUser::create([
            'router_id' => $this->router->id,
            'name' => 'cust_bypass_delete',
            'password' => 'pass123',
            'service' => 'pppoe',
            'profile' => 'default',
            'disabled' => false,
        ]);

        $guard = app(MikrotikSafeModeGuard::class);
        $bypassToken = $guard->createBypassToken($this->router, $this->user->id);

        $response = $this->actingAs($this->user)
            ->withHeader('X-SafeMode-Bypass', $bypassToken)
            ->deleteJson("/api/v1/routers/{$this->router->id}/ppp/secrets/{$user->id}");

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success');

        $this->assertDatabaseMissing('dedicated_users', [
            'id' => $user->id,
        ]);
    }

    public function test_auto_inherits_monthly_price_from_profile_when_creating_with_empty_or_null_price(): void
    {
        $profile = PppProfile::create([
            'router_id' => $this->router->id,
            'name' => 'Auto-Rate-Plan',
            'rate_limit' => '15M/15M',
            'price' => 450000,
            'is_active' => true,
        ]);

        // 1. Create with omitted monthly_price
        $res1 = $this->actingAs($this->user)
            ->postJson("/api/v1/routers/{$this->router->id}/ppp/secrets", [
                'name' => 'cust_auto_rate_1',
                'password' => 'pass12345',
                'service' => 'pppoe',
                'profile' => 'Auto-Rate-Plan',
            ]);

        $res1->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.monthly_price', '450000.00');

        $this->assertDatabaseHas('dedicated_users', [
            'name' => 'cust_auto_rate_1',
            'monthly_price' => 450000.00,
        ]);

        // 2. Create with explicit null monthly_price
        $res2 = $this->actingAs($this->user)
            ->postJson("/api/v1/routers/{$this->router->id}/ppp/secrets", [
                'name' => 'cust_auto_rate_2',
                'password' => 'pass12345',
                'service' => 'pppoe',
                'profile' => 'Auto-Rate-Plan',
                'monthly_price' => null,
            ]);

        $res2->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.monthly_price', '450000.00');

        $this->assertDatabaseHas('dedicated_users', [
            'name' => 'cust_auto_rate_2',
            'monthly_price' => 450000.00,
        ]);
    }

    public function test_auto_inherits_monthly_price_from_profile_when_updating_with_empty_or_null_price(): void
    {
        $profile = PppProfile::create([
            'router_id' => $this->router->id,
            'name' => 'Plan-Update-Test',
            'rate_limit' => '30M/30M',
            'price' => 750000,
            'is_active' => true,
        ]);

        $user = DedicatedUser::create([
            'router_id' => $this->router->id,
            'name' => 'cust_update_price_test',
            'password' => 'pass123',
            'service' => 'pppoe',
            'profile' => 'Plan-Update-Test',
            'monthly_price' => 100000, // custom price initially
            'disabled' => false,
        ]);

        // Update with null monthly_price -> should revert to profile price (750000)
        $res = $this->actingAs($this->user)
            ->putJson("/api/v1/routers/{$this->router->id}/ppp/secrets/{$user->id}", [
                'monthly_price' => null,
            ]);

        $res->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.monthly_price', '750000.00');

        $this->assertDatabaseHas('dedicated_users', [
            'id' => $user->id,
            'monthly_price' => 750000.00,
        ]);
    }
}
