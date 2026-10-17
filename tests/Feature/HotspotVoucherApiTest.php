<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\HotspotProfile;
use App\Models\Router;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherBatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HotspotVoucherApiTest extends TestCase
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
            'name' => 'Gateway-Core-Test',
            'host' => '192.168.88.1',
            'api_port' => 8728,
            'protocol' => 'socket',
            'username' => 'admin',
            'is_safe_mode_enabled' => true,
            'status' => 'online',
        ]);
        $this->router->setPlainPassword('secret123');
        $this->router->save();
    }

    public function test_can_create_and_list_hotspot_profiles(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/routers/{$this->router->id}/profiles", [
                'name' => '5Mbps-Regular',
                'shared_users' => 1,
                'rate_limit' => '2M/5M',
                'validity_duration' => '1',
                'validity_unit' => 'days',
                'price' => 5000,
                'selling_price' => 6000,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.name', '5Mbps-Regular');

        $this->assertDatabaseHas('hotspot_profiles', [
            'router_id' => $this->router->id,
            'name' => '5Mbps-Regular',
            'rate_limit' => '2M/5M',
        ]);

        $list = $this->actingAs($this->user)
            ->getJson("/api/v1/routers/{$this->router->id}/profiles");

        $list->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonCount(1, 'data');
    }

    public function test_can_generate_voucher_batch_without_mikrotik_sync_in_test(): void
    {
        $profile = HotspotProfile::create([
            'router_id' => $this->router->id,
            'name' => '1Hour-Test',
            'shared_users' => 1,
            'rate_limit' => '1M/2M',
            'validity_duration' => '1',
            'validity_unit' => 'hours',
            'price' => 3000,
            'selling_price' => 3000,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/routers/{$this->router->id}/vouchers/generate", [
                'name' => 'Batch-Promo-10',
                'profile_id' => $profile->id,
                'quantity' => 10,
                'code_format' => 'numeric',
                'character_length' => 6,
                'prefix' => 'VIP',
                'password_mode' => 'same_as_username',
                'time_limit' => '1h',
                'data_limit_mb' => 500,
                'sync_to_mikrotik' => false,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.vouchers_count', 10);

        $this->assertDatabaseCount('voucher_batches', 1);
        $this->assertDatabaseCount('vouchers', 10);

        $voucher = Voucher::first();
        $this->assertNotNull($voucher);
        $this->assertStringStartsWith('VIP', $voucher->username);
        $this->assertEquals($voucher->username, $voucher->password);
        $this->assertEquals(500 * 1024 * 1024, $voucher->data_limit_bytes);

        // Test fetching batch detail
        $batch = VoucherBatch::first();
        $detailRes = $this->actingAs($this->user)
            ->getJson("/api/v1/routers/{$this->router->id}/vouchers/batches/{$batch->id}");

        $detailRes->assertStatus(200)
            ->assertJsonPath('data.id', $batch->id)
            ->assertJsonCount(10, 'data.vouchers');
    }

    public function test_can_mark_vouchers_as_printed(): void
    {
        $profile = HotspotProfile::create([
            'router_id' => $this->router->id,
            'name' => '1Day',
            'is_active' => true,
        ]);

        $batch = VoucherBatch::create([
            'router_id' => $this->router->id,
            'profile_id' => $profile->id,
            'name' => 'Batch-Print-Test',
            'quantity' => 2,
            'character_length' => 6,
            'code_format' => 'numeric',
            'password_mode' => 'same_as_username',
            'created_by' => $this->user->id,
        ]);

        $v1 = Voucher::create([
            'batch_id' => $batch->id,
            'router_id' => $this->router->id,
            'profile_id' => $profile->id,
            'username' => 'test01',
            'password' => 'test01',
            'status' => 'active',
            'is_printed' => false,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/routers/{$this->router->id}/vouchers/mark-printed", [
                'batch_id' => $batch->id,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success');

        $this->assertTrue($v1->fresh()->is_printed);
    }

    public function test_bulk_delete_vouchers_is_blocked_by_safe_mode(): void
    {
        $profile = HotspotProfile::create([
            'router_id' => $this->router->id,
            'name' => 'Profile-Del',
            'is_active' => true,
        ]);

        $voucher = Voucher::create([
            'router_id' => $this->router->id,
            'profile_id' => $profile->id,
            'username' => 'del01',
            'password' => 'del01',
            'status' => 'active',
        ]);

        // Attempt bulk delete without bypass token
        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/routers/{$this->router->id}/vouchers/bulk-delete", [
                'voucher_ids' => [$voucher->id],
            ]);

        $response->assertStatus(403)
            ->assertJsonPath('code', 'SAFE_MODE_ACTIVE');

        // Ensure voucher still exists
        $this->assertDatabaseHas('vouchers', ['id' => $voucher->id]);
    }

    public function test_bulk_delete_vouchers_succeeds_with_bypass_token(): void
    {
        $profile = HotspotProfile::create([
            'router_id' => $this->router->id,
            'name' => 'Profile-Del-Allowed',
            'is_active' => true,
        ]);

        $voucher = Voucher::create([
            'router_id' => $this->router->id,
            'profile_id' => $profile->id,
            'username' => 'del-ok-01',
            'password' => 'del-ok-01',
            'status' => 'active',
        ]);

        // Generate bypass token
        $unlockRes = $this->actingAs($this->user)
            ->postJson("/api/v1/routers/{$this->router->id}/safe-mode/unlock", [
                'password' => 'password',
                'verification_text' => 'UNLOCK',
                'reason' => 'Testing bulk removal',
            ]);

        $bypassToken = $unlockRes->json('data.bypass_token');
        $this->assertNotEmpty($bypassToken);

        $deleteRes = $this->actingAs($this->user)
            ->withHeader('X-SafeMode-Bypass', $bypassToken)
            ->postJson("/api/v1/routers/{$this->router->id}/vouchers/bulk-delete", [
                'voucher_ids' => [$voucher->id],
            ]);

        $deleteRes->assertStatus(200)
            ->assertJsonPath('status', 'success');

        $this->assertDatabaseMissing('vouchers', ['id' => $voucher->id]);
    }
}
