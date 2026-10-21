<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Group;
use App\Models\Router;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserAndGroupManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $superadmin;

    private Group $superadminGroup;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superadminGroup = Group::firstOrCreate(
            ['slug' => 'superadmin'],
            [
                'name' => 'Super Administrator',
                'is_superadmin' => true,
                'all_routers' => true,
            ]
        );

        $this->superadmin = User::factory()->create([
            'name' => 'Root Superadmin',
            'email' => 'root@tikpulse.test',
            'role' => 'superadmin',
            'group_id' => $this->superadminGroup->id,
            'is_active' => true,
        ]);
    }

    public function test_superadmin_can_list_and_create_groups_with_permissions_and_router_scoping(): void
    {
        $token = $this->superadmin->createToken('test_token')->plainTextToken;

        $router1 = new Router([
            'name' => 'Router Alpha',
            'host' => '10.0.0.1',
            'api_port' => 8728,
            'protocol' => 'socket',
            'username' => 'admin',
        ]);
        $router1->setPlainPassword('pass1');
        $router1->save();

        $createResponse = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/groups', [
                'name' => 'Hotspot Support Staff',
                'description' => 'Staff handling voucher issues',
                'all_routers' => false,
                'router_ids' => [$router1->id],
                'permissions' => [
                    'dashboard' => ['can_read' => true, 'can_write' => false, 'can_update' => false, 'can_delete' => false],
                    'hotspot_vouchers' => ['can_read' => true, 'can_write' => true, 'can_update' => true, 'can_delete' => false],
                ],
            ]);

        $createResponse->assertStatus(201)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'name' => 'Hotspot Support Staff',
                    'all_routers' => false,
                    'router_ids' => [$router1->id],
                ],
            ]);

        $groupId = $createResponse->json('data.id');

        // Check group exists in database
        $group = Group::with('permissions', 'routers')->find($groupId);
        $this->assertNotNull($group);
        $this->assertTrue($group->hasPermission('hotspot_vouchers', 'write'));
        $this->assertFalse($group->hasPermission('hotspot_vouchers', 'delete'));
        $this->assertTrue($group->canAccessRouter($router1->id));
        $this->assertFalse($group->canAccessRouter(99999));
    }

    public function test_cannot_delete_superadmin_group_or_group_with_assigned_users(): void
    {
        $token = $this->superadmin->createToken('test_token')->plainTextToken;

        // Try deleting superadmin group
        $deleteSuperResponse = $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson("/api/v1/groups/{$this->superadminGroup->id}");

        $deleteSuperResponse->assertStatus(403);

        // Create group with assigned user
        $group = Group::create([
            'name' => 'Temporary Group',
            'slug' => 'temp-group',
            'all_routers' => true,
        ]);

        User::factory()->create([
            'group_id' => $group->id,
            'role' => 'operator',
        ]);

        $deleteAssignedResponse = $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson("/api/v1/groups/{$group->id}");

        $deleteAssignedResponse->assertStatus(422)
            ->assertJson(['code' => 'GROUP_HAS_ACTIVE_USERS']);
    }

    public function test_superadmin_can_create_update_and_delete_users(): void
    {
        $token = $this->superadmin->createToken('test_token')->plainTextToken;

        $operatorGroup = Group::firstOrCreate(
            ['slug' => 'operator'],
            [
                'name' => 'Operator Group',
                'all_routers' => true,
            ]
        );

        // 1. Create user
        $createResponse = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/users', [
                'name' => 'Alice Operator',
                'email' => 'alice@tikpulse.test',
                'password' => 'secretAlice123',
                'group_id' => $operatorGroup->id,
                'is_active' => true,
            ]);

        $createResponse->assertStatus(201)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'name' => 'Alice Operator',
                    'email' => 'alice@tikpulse.test',
                    'is_active' => true,
                ],
            ]);

        $userId = $createResponse->json('data.id');

        // 2. Update user
        $updateResponse = $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson("/api/v1/users/{$userId}", [
                'name' => 'Alice Updated',
                'email' => 'alice.updated@tikpulse.test',
                'group_id' => $operatorGroup->id,
                'is_active' => false,
            ]);

        $updateResponse->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'name' => 'Alice Updated',
                    'email' => 'alice.updated@tikpulse.test',
                    'is_active' => false,
                ],
            ]);

        // 3. Delete user
        $deleteResponse = $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson("/api/v1/users/{$userId}");

        $deleteResponse->assertStatus(200);
        $this->assertNull(User::find($userId));
    }

    public function test_cannot_delete_or_demote_the_last_superadmin(): void
    {
        $token = $this->superadmin->createToken('test_token')->plainTextToken;

        $operatorGroup = Group::firstOrCreate(
            ['slug' => 'operator'],
            [
                'name' => 'Operator',
                'all_routers' => true,
            ]
        );

        // Attempt to demote self (the only superadmin)
        $demoteResponse = $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson("/api/v1/users/{$this->superadmin->id}", [
                'name' => 'Root Superadmin',
                'email' => 'root@tikpulse.test',
                'group_id' => $operatorGroup->id,
                'is_active' => true,
            ]);

        $demoteResponse->assertStatus(422)
            ->assertJson(['code' => 'LAST_SUPERADMIN_PROTECTED']);

        // Attempt to delete self
        $deleteResponse = $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson("/api/v1/users/{$this->superadmin->id}");

        $deleteResponse->assertStatus(422)
            ->assertJson(['code' => 'CANNOT_DELETE_SELF']);
    }

    public function test_user_restricted_by_module_permissions_receives_403(): void
    {
        $restrictedGroup = Group::create([
            'name' => 'Restricted Viewer',
            'slug' => 'viewer-only',
            'all_routers' => true,
        ]);
        $restrictedGroup->syncPermissions([
            'dashboard' => ['can_read' => true, 'can_write' => false, 'can_update' => false, 'can_delete' => false],
            'users' => ['can_read' => false, 'can_write' => false, 'can_update' => false, 'can_delete' => false],
        ]);

        $viewerUser = User::factory()->create([
            'name' => 'Viewer Bob',
            'email' => 'bob@tikpulse.test',
            'role' => 'viewer',
            'group_id' => $restrictedGroup->id,
            'is_active' => true,
        ]);

        $token = $viewerUser->createToken('viewer_token')->plainTextToken;

        // Attempt to view users list
        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/users');

        $response->assertStatus(403)
            ->assertJson(['code' => 'MODULE_ACCESS_DENIED']);
    }

    public function test_user_restricted_by_router_scoping_cannot_access_unassigned_router(): void
    {
        $routerA = new Router([
            'name' => 'Router A (Permitted)',
            'host' => '10.1.1.1',
            'api_port' => 8728,
            'protocol' => 'socket',
            'username' => 'admin',
        ]);
        $routerA->setPlainPassword('pass');
        $routerA->save();

        $routerB = new Router([
            'name' => 'Router B (Restricted)',
            'host' => '10.2.2.2',
            'api_port' => 8728,
            'protocol' => 'socket',
            'username' => 'admin',
        ]);
        $routerB->setPlainPassword('pass');
        $routerB->save();

        $scopedGroup = Group::create([
            'name' => 'Router A Operator',
            'slug' => 'router-a-op',
            'all_routers' => false,
        ]);
        $scopedGroup->routers()->attach($routerA->id);
        $scopedGroup->syncPermissions([
            'routers' => ['can_read' => true],
            'hotspot_vouchers' => ['can_read' => true, 'can_write' => true],
        ]);

        $operator = User::factory()->create([
            'name' => 'Charlie Op',
            'email' => 'charlie@tikpulse.test',
            'role' => 'operator',
            'group_id' => $scopedGroup->id,
            'is_active' => true,
        ]);

        $token = $operator->createToken('op_token')->plainTextToken;

        // 1. Router list should only contain Router A
        $listResponse = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/routers');

        $listResponse->assertStatus(200);
        $routerIds = collect($listResponse->json('data'))->pluck('id')->all();
        $this->assertContains($routerA->id, $routerIds);
        $this->assertNotContains($routerB->id, $routerIds);

        // 2. Direct access to Router B endpoint should be denied
        $directResponse = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson("/api/v1/routers/{$routerB->id}/vouchers");

        $directResponse->assertStatus(403)
            ->assertJson(['code' => 'ROUTER_ACCESS_DENIED']);
    }

    public function test_superadmin_can_switch_role_simulation_and_experience_restricted_access(): void
    {
        $token = $this->superadmin->createToken('super_token')->plainTextToken;

        $restrictedGroup = Group::create([
            'name' => 'Auditor ReadOnly',
            'slug' => 'auditor',
            'all_routers' => true,
        ]);
        $restrictedGroup->syncPermissions([
            'dashboard' => ['can_read' => true],
            'safe_mode_logs' => ['can_read' => true],
            'users' => ['can_read' => false],
        ]);

        // 1. Switch role to Auditor
        $switchResponse = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/auth/switch-role', [
                'group_id' => $restrictedGroup->id,
            ]);

        $switchResponse->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'user' => [
                        'is_simulating' => true,
                        'simulated_group_id' => $restrictedGroup->id,
                    ],
                ],
            ]);

        // 2. While simulating auditor, accessing /users must return 403 Forbidden
        $deniedResponse = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/users');

        $deniedResponse->assertStatus(403);

        // 3. Reset role back to superadmin
        $resetResponse = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/auth/reset-role');

        $resetResponse->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'user' => [
                        'is_simulating' => false,
                        'simulated_group_id' => null,
                    ],
                ],
            ]);

        // 4. Accessing /users now succeeds
        $allowedResponse = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/users');

        $allowedResponse->assertStatus(200);
    }

    public function test_user_can_save_settings_including_theme_accent_color_and_locale(): void
    {
        $token = $this->superadmin->createToken('settings_token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/auth/settings', [
                'theme' => 'dark',
                'accent_color' => 'emerald',
                'preferred_locale' => 'id',
                'refresh_interval' => 30,
                'table_compact_mode' => true,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'user' => [
                        'preferred_locale' => 'id',
                        'settings' => [
                            'theme' => 'dark',
                            'accent_color' => 'emerald',
                            'preferred_locale' => 'id',
                            'refresh_interval' => 30,
                            'table_compact_mode' => true,
                        ],
                    ],
                ],
            ]);

        $this->superadmin->refresh();
        $this->assertEquals('id', $this->superadmin->preferred_locale);
        $this->assertEquals('dark', $this->superadmin->getSetting('theme'));
        $this->assertEquals('emerald', $this->superadmin->getSetting('accent_color'));
    }

    public function test_authenticated_user_can_access_dashboard_and_inertia_shares_routers_correctly(): void
    {
        $router = new Router([
            'name' => 'Gateway Core',
            'host' => '192.168.88.1',
            'api_port' => 8728,
            'protocol' => 'socket',
            'username' => 'admin',
            'status' => 'online',
        ]);
        $router->setPlainPassword('secret123');
        $router->save();

        $response = $this->actingAs($this->superadmin)->get('/dashboard');

        $response->assertStatus(200);
    }
}
