<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Fortify\ResetUserPassword;
use App\Infrastructure\Network\Mikrotik\Services\HotspotVoucherService;
use App\Infrastructure\Network\Mikrotik\Services\PppService;
use App\Models\DedicatedUser;
use App\Models\HotspotProfile;
use App\Models\Router;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class CustomerAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_pppoe_customer_can_login_with_username_and_password(): void
    {
        $router = new Router([
            'name' => 'Main Router',
            'host' => '10.0.0.1',
            'api_port' => 8728,
            'protocol' => 'socket',
            'username' => 'admin',
        ]);
        $router->setPlainPassword('secret');
        $router->save();

        DedicatedUser::create([
            'router_id' => $router->id,
            'name' => 'pppoe-demo',
            'password' => 'demopass123',
            'service' => 'pppoe',
            'profile' => 'default',
            'disabled' => false,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'login' => 'pppoe-demo',
            'password' => 'demopass123',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'account_type' => 'ppp_customer',
                    'user' => [
                        'name' => 'pppoe-demo',
                        'role' => 'customer',
                    ],
                ],
            ]);

        $token = $response->json('data.token');
        $this->assertNotEmpty($token);

        // Verify customer status route works
        $statusRes = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/customer/status');

        $statusRes->assertStatus(200)
            ->assertJson([
                'status' => 'success',
            ]);
    }

    public function test_hotspot_customer_can_login_when_providing_email_key_with_username(): void
    {
        $router = new Router([
            'name' => 'Main Router',
            'host' => '10.0.0.1',
            'api_port' => 8728,
            'protocol' => 'socket',
            'username' => 'admin',
        ]);
        $router->setPlainPassword('secret');
        $router->save();

        $profile = HotspotProfile::create([
            'router_id' => $router->id,
            'name' => '5Mbps-Regular',
            'shared_users' => 1,
            'rate_limit' => '2M/5M',
        ]);

        Voucher::create([
            'router_id' => $router->id,
            'profile_id' => $profile->id,
            'code' => 'voucher-101',
            'username' => 'voucher-101',
            'password' => 'pass101',
            'status' => 'active',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'voucher-101',
            'password' => 'pass101',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'account_type' => 'hotspot_customer',
                    'user' => [
                        'name' => 'voucher-101',
                        'role' => 'customer',
                    ],
                ],
            ]);
    }

    public function test_customer_redirected_to_status_when_visiting_root_or_dashboard(): void
    {
        $customer = User::factory()->create([
            'role' => 'customer',
        ]);

        $this->actingAs($customer)
            ->get('/')
            ->assertRedirect('/status');

        $this->actingAs($customer)
            ->get('/dashboard')
            ->assertRedirect('/status');
    }

    public function test_customer_can_logout_via_api_and_token_is_revoked(): void
    {
        $customer = User::factory()->create([
            'role' => 'customer',
        ]);
        $token = $customer->createToken('tikpulse_auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->withCookie('tikpulse_token', $token)
            ->postJson('/api/v1/customer/logout');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
            ]);

        // Token should now be deleted
        $this->assertNull(PersonalAccessToken::findToken($token));

        $this->app['auth']->forgetGuards();

        // Submitting with that token now fails with 401
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/customer/status')
            ->assertStatus(401);
    }

    public function test_customer_can_logout_via_web_route(): void
    {
        $customer = User::factory()->create([
            'role' => 'customer',
        ]);

        $response = $this->actingAs($customer)
            ->post('/logout');

        $response->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_ppp_customer_can_update_profile_and_contact_info(): void
    {
        $router = new Router([
            'name' => 'Profile Router',
            'host' => '10.0.0.1',
            'api_port' => 8728,
            'protocol' => 'socket',
            'username' => 'admin',
        ]);
        $router->setPlainPassword('secret');
        $router->save();

        $dedicated = DedicatedUser::create([
            'router_id' => $router->id,
            'name' => 'client-update',
            'password' => 'secret123',
            'customer_name' => 'Old Name',
            'customer_phone' => '0811111111',
            'customer_address' => 'Old Address',
            'service' => 'pppoe',
            'profile' => 'default',
            'disabled' => false,
        ]);

        $customer = User::factory()->create([
            'name' => 'client-update',
            'role' => 'customer',
            'preferred_locale' => 'id',
            'settings' => [
                'account_type' => 'ppp_customer',
                'router_id' => $router->id,
            ],
        ]);

        $response = $this->actingAs($customer)
            ->putJson('/api/v1/customer/profile', [
                'customer_name' => 'Budi Santoso',
                'customer_phone' => '081234567890',
                'customer_address' => 'Jl. Merdeka No. 45',
                'preferred_locale' => 'en',
                'theme' => 'dark',
                'accent_color' => 'emerald',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
            ]);

        $this->assertDatabaseHas('dedicated_users', [
            'id' => $dedicated->id,
            'customer_name' => 'Budi Santoso',
            'customer_phone' => '081234567890',
            'customer_address' => 'Jl. Merdeka No. 45',
        ]);

        $customer->refresh();
        $this->assertEquals('en', $customer->preferred_locale);
        $this->assertEquals('dark', $customer->settings['theme']);
        $this->assertEquals('emerald', $customer->settings['accent_color']);
    }

    public function test_customer_can_update_password_without_modifying_router_credentials(): void
    {
        $router = new Router([
            'name' => 'Sync Router',
            'host' => '10.0.0.1',
            'api_port' => 8728,
            'protocol' => 'socket',
            'username' => 'admin',
        ]);
        $router->setPlainPassword('secret');
        $router->save();

        $dedicated = DedicatedUser::create([
            'router_id' => $router->id,
            'name' => 'client-pass',
            'password' => 'oldpassword',
            'service' => 'pppoe',
            'profile' => 'default',
            'disabled' => false,
        ]);

        $customer = User::factory()->create([
            'name' => 'client-pass',
            'password' => Hash::make('oldpassword'),
            'role' => 'customer',
            'settings' => [
                'account_type' => 'ppp_customer',
                'router_id' => $router->id,
            ],
        ]);

        // MikroTik router must NOT be touched when portal password changes
        $mockPpp = $this->createMock(PppService::class);
        $mockPpp->expects($this->never())
            ->method('updateSecret');
        $this->app->instance(PppService::class, $mockPpp);

        $response = $this->actingAs($customer)
            ->putJson('/api/v1/customer/profile', [
                'current_password' => 'oldpassword',
                'new_password' => 'brandnewpass123',
                'new_password_confirmation' => 'brandnewpass123',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
            ]);

        $customer->refresh();
        $this->assertTrue(Hash::check('brandnewpass123', $customer->password));

        // Router secret in dedicated_users must remain unchanged
        $this->assertDatabaseHas('dedicated_users', [
            'id' => $dedicated->id,
            'password' => 'oldpassword',
        ]);

        // Status endpoint still returns router secret as default_password
        $statusRes = $this->actingAs($customer)->getJson('/api/v1/customer/status');
        $statusRes->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'default_password' => 'oldpassword',
                ],
            ]);
    }

    public function test_customer_cannot_update_password_with_wrong_current_password(): void
    {
        $customer = User::factory()->create([
            'name' => 'client-fail',
            'password' => Hash::make('correctpassword'),
            'role' => 'customer',
            'settings' => [
                'account_type' => 'ppp_customer',
            ],
        ]);

        $response = $this->actingAs($customer)
            ->putJson('/api/v1/customer/profile', [
                'current_password' => 'wrongpassword',
                'new_password' => 'brandnewpass123',
                'new_password_confirmation' => 'brandnewpass123',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['current_password']);
    }

    public function test_hotspot_customer_can_update_password_without_modifying_router_credentials(): void
    {
        $router = new Router([
            'name' => 'Hotspot Sync Router',
            'host' => '10.0.0.1',
            'api_port' => 8728,
            'protocol' => 'socket',
            'username' => 'admin',
        ]);
        $router->setPlainPassword('secret');
        $router->save();

        $profile = HotspotProfile::create([
            'router_id' => $router->id,
            'name' => 'default',
            'shared_users' => 1,
        ]);

        $voucher = Voucher::create([
            'router_id' => $router->id,
            'profile_id' => $profile->id,
            'code' => 'hs-customer',
            'username' => 'hs-customer',
            'password' => 'oldpassword',
            'status' => 'active',
        ]);

        $customer = User::factory()->create([
            'name' => 'hs-customer',
            'password' => Hash::make('oldpassword'),
            'role' => 'customer',
            'settings' => [
                'account_type' => 'hotspot_customer',
                'router_id' => $router->id,
            ],
        ]);

        // MikroTik hotspot user must NOT be touched
        $mockHs = $this->createMock(HotspotVoucherService::class);
        $mockHs->expects($this->never())
            ->method('updateHotspotUserPassword');
        $this->app->instance(HotspotVoucherService::class, $mockHs);

        $response = $this->actingAs($customer)
            ->putJson('/api/v1/customer/profile', [
                'current_password' => 'oldpassword',
                'new_password' => 'newhotspotpass123',
                'new_password_confirmation' => 'newhotspotpass123',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
            ]);

        $customer->refresh();
        $this->assertTrue(Hash::check('newhotspotpass123', $customer->password));

        // Voucher password in database remains unchanged
        $this->assertDatabaseHas('vouchers', [
            'id' => $voucher->id,
            'password' => 'oldpassword',
        ]);
    }

    public function test_operator_reset_customer_password_updates_portal_only_and_does_not_modify_router(): void
    {
        $superadmin = User::factory()->create([
            'role' => 'superadmin',
        ]);

        $router = new Router([
            'name' => 'Operator Reset Router',
            'host' => '10.0.0.1',
            'api_port' => 8728,
            'protocol' => 'socket',
            'username' => 'admin',
        ]);
        $router->setPlainPassword('secret');
        $router->save();

        $dedicated = DedicatedUser::create([
            'router_id' => $router->id,
            'name' => 'pppoe-cust-reset',
            'password' => 'oldpass123',
            'service' => 'pppoe',
            'profile' => 'default',
            'disabled' => false,
        ]);

        $customer = User::factory()->create([
            'name' => 'pppoe-cust-reset',
            'password' => Hash::make('oldpass123'),
            'role' => 'customer',
            'settings' => [
                'account_type' => 'ppp_customer',
                'router_id' => $router->id,
            ],
        ]);

        $mockPpp = $this->createMock(PppService::class);
        $mockPpp->expects($this->never())
            ->method('updateSecret');
        $this->app->instance(PppService::class, $mockPpp);

        $response = $this->actingAs($superadmin)
            ->postJson("/api/v1/users/{$customer->id}/reset-password", [
                'password' => 'adminreset999',
                'password_confirmation' => 'adminreset999',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'temporary_password' => 'adminreset999',
                    'synced_to_router' => false,
                ],
            ]);

        $customer->refresh();
        $this->assertTrue(Hash::check('adminreset999', $customer->password));

        // Dedicated user router secret remains unchanged
        $this->assertDatabaseHas('dedicated_users', [
            'id' => $dedicated->id,
            'password' => 'oldpass123',
        ]);
    }

    public function test_fortify_forgot_password_reset_updates_portal_only_and_does_not_modify_router(): void
    {
        $router = new Router([
            'name' => 'Fortify Reset Router',
            'host' => '10.0.0.1',
            'api_port' => 8728,
            'protocol' => 'socket',
            'username' => 'admin',
        ]);
        $router->setPlainPassword('secret');
        $router->save();

        $dedicated = DedicatedUser::create([
            'router_id' => $router->id,
            'name' => 'fortify-cust',
            'password' => 'oldpass',
            'service' => 'pppoe',
            'profile' => 'default',
            'disabled' => false,
        ]);

        $customer = User::factory()->create([
            'name' => 'fortify-cust',
            'password' => Hash::make('oldpass'),
            'role' => 'customer',
            'settings' => [
                'account_type' => 'ppp_customer',
                'router_id' => $router->id,
            ],
        ]);

        $mockPpp = $this->createMock(PppService::class);
        $mockPpp->expects($this->never())
            ->method('updateSecret');
        $this->app->instance(PppService::class, $mockPpp);

        $action = app(ResetUserPassword::class);
        $action->reset($customer, [
            'password' => 'fortifyreset123',
            'password_confirmation' => 'fortifyreset123',
        ]);

        $customer->refresh();
        $this->assertTrue(Hash::check('fortifyreset123', $customer->password));

        // Router secret in dedicated_users must remain unchanged
        $this->assertDatabaseHas('dedicated_users', [
            'id' => $dedicated->id,
            'password' => 'oldpass',
        ]);
    }

    public function test_customer_can_update_email_address_and_resets_verification(): void
    {
        $router = new Router([
            'name' => 'Email Router',
            'host' => '10.0.0.1',
            'api_port' => 8728,
            'protocol' => 'socket',
            'username' => 'admin',
        ]);
        $router->setPlainPassword('secret');
        $router->save();

        DedicatedUser::create([
            'router_id' => $router->id,
            'name' => 'email-client',
            'password' => 'pass123',
            'service' => 'pppoe',
            'profile' => 'default',
            'disabled' => false,
        ]);

        $customer = User::factory()->create([
            'name' => 'email-client',
            'email' => 'ppp_1_email-client@customer.tikpulse.local',
            'email_verified_at' => now(),
            'role' => 'customer',
            'settings' => [
                'account_type' => 'ppp_customer',
                'router_id' => $router->id,
            ],
        ]);

        $response = $this->actingAs($customer)
            ->putJson('/api/v1/customer/profile', [
                'email' => 'new.customer@example.com',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'user' => [
                        'email' => 'new.customer@example.com',
                        'is_email_verified' => false,
                    ],
                ],
            ]);

        $customer->refresh();
        $this->assertEquals('new.customer@example.com', $customer->email);
        $this->assertNull($customer->email_verified_at);

        // Status endpoint also returns the updated email and verification state
        $statusRes = $this->actingAs($customer)
            ->getJson('/api/v1/customer/status');

        $statusRes->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'email' => 'new.customer@example.com',
                    'is_email_verified' => false,
                ],
            ]);
    }

    public function test_customer_cannot_update_email_to_already_registered_address(): void
    {
        User::factory()->create([
            'email' => 'existing@example.com',
        ]);

        $customer = User::factory()->create([
            'name' => 'unique-cust',
            'email' => 'cust@example.com',
            'role' => 'customer',
        ]);

        $response = $this->actingAs($customer)
            ->putJson('/api/v1/customer/profile', [
                'email' => 'existing@example.com',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_customer_can_keep_same_email_without_validation_conflict(): void
    {
        $customer = User::factory()->create([
            'name' => 'same-email-cust',
            'email' => 'cust.same@example.com',
            'role' => 'customer',
        ]);

        $response = $this->actingAs($customer)
            ->putJson('/api/v1/customer/profile', [
                'email' => 'cust.same@example.com',
                'customer_name' => 'Updated Name Only',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
            ]);

        $customer->refresh();
        $this->assertEquals('cust.same@example.com', $customer->email);
    }

    public function test_customer_verify_email_flow_persists_verification_on_save(): void
    {
        $customer = User::factory()->create([
            'name' => 'verify-flow-cust',
            'email' => 'old@example.com',
            'email_verified_at' => null,
            'role' => 'customer',
        ]);

        // 1. Resend/request with new email
        $res1 = $this->actingAs($customer, 'sanctum')
            ->postJson('/api/v1/email/verification-notification', [
                'email' => 'newverified@example.com',
            ]);
        $res1->assertStatus(200);

        $cached = Cache::get("email_verify_code_{$customer->id}");
        $this->assertNotNull($cached);
        $code = $cached['code'];

        // 2. Verify with code
        $res2 = $this->actingAs($customer, 'sanctum')
            ->postJson('/api/v1/email/verify-code', [
                'code' => $code,
            ]);
        $res2->assertStatus(200);
        $this->assertTrue($customer->fresh()->hasVerifiedEmail());

        // 3. Save profile with the newly verified email
        $res3 = $this->actingAs($customer, 'sanctum')
            ->putJson('/api/v1/customer/profile', [
                'email' => 'newverified@example.com',
                'preferred_locale' => 'id',
            ]);
        $res3->assertStatus(200);

        $fresh = $customer->fresh();
        $this->assertEquals('newverified@example.com', $fresh->email);
        $this->assertTrue($fresh->hasVerifiedEmail(), 'Email should still be verified after saving profile!');
    }
}
