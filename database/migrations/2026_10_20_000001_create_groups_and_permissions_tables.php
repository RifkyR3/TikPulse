<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Create groups table
        Schema::create('groups', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 50)->unique();
            $table->text('description')->nullable();
            $table->boolean('is_superadmin')->default(false);
            $table->boolean('all_routers')->default(false);
            $table->timestamps();
        });

        // 2. Create modules table
        Schema::create('modules', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 100);
            $table->string('key', 50)->unique();
            $table->text('description')->nullable();
            $table->jsonb('available_actions')->default(json_encode(['read', 'write', 'update', 'delete']));
            $table->timestamps();
        });

        // 3. Create group_module_permissions table
        Schema::create('group_module_permissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('group_id')->constrained('groups')->cascadeOnDelete();
            $table->string('module_key', 50);
            $table->boolean('can_read')->default(false);
            $table->boolean('can_write')->default(false);
            $table->boolean('can_update')->default(false);
            $table->boolean('can_delete')->default(false);
            $table->timestamps();

            $table->unique(['group_id', 'module_key']);
            $table->index('group_id');
        });

        // 4. Create group_router pivot table
        Schema::create('group_router', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('group_id')->constrained('groups')->cascadeOnDelete();
            $table->foreignId('router_id')->constrained('routers')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['group_id', 'router_id']);
            $table->index('group_id');
            $table->index('router_id');
        });

        // 5. Update users table with group_id, settings, and simulated_group_id
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'group_id')) {
                $table->foreignId('group_id')->nullable()->after('role')->constrained('groups')->nullOnDelete();
            }
            if (! Schema::hasColumn('users', 'settings')) {
                $table->jsonb('settings')->default(json_encode([
                    'theme' => 'system',
                    'accent_color' => 'sky',
                    'refresh_interval' => 15,
                    'table_compact_mode' => false,
                ]))->after('preferred_locale');
            }
            if (! Schema::hasColumn('users', 'simulated_group_id')) {
                $table->foreignId('simulated_group_id')->nullable()->after('settings')->constrained('groups')->nullOnDelete();
            }
        });

        // 6. Seed initial modules
        $modules = [
            [
                'name' => 'Dashboard Overview',
                'key' => 'dashboard',
                'description' => 'Real-time router metrics, bandwidth traffic, and voucher usage stats.',
                'available_actions' => json_encode(['read']),
            ],
            [
                'name' => 'Router Management',
                'key' => 'routers',
                'description' => 'MikroTik router connections, telemetry polling, and safe mode controls.',
                'available_actions' => json_encode(['read', 'write', 'update', 'delete']),
            ],
            [
                'name' => 'Hotspot & Vouchers',
                'key' => 'hotspot_vouchers',
                'description' => 'Hotspot user accounts, bandwidth profiles, and batch voucher generator.',
                'available_actions' => json_encode(['read', 'write', 'update', 'delete']),
            ],
            [
                'name' => 'PPP & Dedicated Users',
                'key' => 'ppp_subscribers',
                'description' => 'PPPoE/PPP dedicated secrets, bandwidth tiers, and active sessions.',
                'available_actions' => json_encode(['read', 'write', 'update', 'delete']),
            ],
            [
                'name' => 'Safe Mode & Security Logs',
                'key' => 'safe_mode_logs',
                'description' => 'Audit trails for safe mode guardrail actions and bypass events.',
                'available_actions' => json_encode(['read', 'write', 'delete']),
            ],
            [
                'name' => 'User Management',
                'key' => 'users',
                'description' => 'Operator and administrator user accounts, access, and credentials.',
                'available_actions' => json_encode(['read', 'write', 'update', 'delete']),
            ],
            [
                'name' => 'Group & Module Permissions',
                'key' => 'groups',
                'description' => 'Group roles, granular module permissions, and MikroTik router scoping.',
                'available_actions' => json_encode(['read', 'write', 'update', 'delete']),
            ],
        ];

        foreach ($modules as $mod) {
            DB::table('modules')->insertOrIgnore([
                'name' => $mod['name'],
                'key' => $mod['key'],
                'description' => $mod['description'],
                'available_actions' => $mod['available_actions'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 7. Seed initial groups
        $superadminGroupId = DB::table('groups')->insertGetId([
            'name' => 'Super Administrator',
            'slug' => 'superadmin',
            'description' => 'Full unrestricted access to all modules, all MikroTik routers, and role switching.',
            'is_superadmin' => true,
            'all_routers' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $adminGroupId = DB::table('groups')->insertGetId([
            'name' => 'Administrator',
            'slug' => 'admin',
            'description' => 'Full administrative access across all routers and management modules.',
            'is_superadmin' => false,
            'all_routers' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $operatorGroupId = DB::table('groups')->insertGetId([
            'name' => 'Hotspot & PPP Operator',
            'slug' => 'operator',
            'description' => 'Access to generate vouchers, manage subscribers, and view router status.',
            'is_superadmin' => false,
            'all_routers' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $viewerGroupId = DB::table('groups')->insertGetId([
            'name' => 'Read-Only Viewer',
            'slug' => 'viewer',
            'description' => 'Read-only monitoring access to dashboard, vouchers, and subscribers.',
            'is_superadmin' => false,
            'all_routers' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Grant full permissions for superadmin & admin to all modules
        $allModuleKeys = array_column($modules, 'key');
        foreach ($allModuleKeys as $key) {
            DB::table('group_module_permissions')->insert([
                'group_id' => $superadminGroupId,
                'module_key' => $key,
                'can_read' => true,
                'can_write' => true,
                'can_update' => true,
                'can_delete' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('group_module_permissions')->insert([
                'group_id' => $adminGroupId,
                'module_key' => $key,
                'can_read' => true,
                'can_write' => true,
                'can_update' => true,
                'can_delete' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Grant operator permissions
        $operatorPermissions = [
            'dashboard' => ['can_read' => true, 'can_write' => false, 'can_update' => false, 'can_delete' => false],
            'routers' => ['can_read' => true, 'can_write' => false, 'can_update' => false, 'can_delete' => false],
            'hotspot_vouchers' => ['can_read' => true, 'can_write' => true, 'can_update' => true, 'can_delete' => false],
            'ppp_subscribers' => ['can_read' => true, 'can_write' => true, 'can_update' => true, 'can_delete' => false],
            'safe_mode_logs' => ['can_read' => true, 'can_write' => false, 'can_update' => false, 'can_delete' => false],
            'users' => ['can_read' => false, 'can_write' => false, 'can_update' => false, 'can_delete' => false],
            'groups' => ['can_read' => false, 'can_write' => false, 'can_update' => false, 'can_delete' => false],
        ];

        foreach ($operatorPermissions as $modKey => $perms) {
            DB::table('group_module_permissions')->insert([
                'group_id' => $operatorGroupId,
                'module_key' => $modKey,
                'can_read' => $perms['can_read'],
                'can_write' => $perms['can_write'],
                'can_update' => $perms['can_update'],
                'can_delete' => $perms['can_delete'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Grant viewer permissions
        foreach ($allModuleKeys as $key) {
            DB::table('group_module_permissions')->insert([
                'group_id' => $viewerGroupId,
                'module_key' => $key,
                'can_read' => in_array($key, ['dashboard', 'routers', 'hotspot_vouchers', 'ppp_subscribers', 'safe_mode_logs'], true),
                'can_write' => false,
                'can_update' => false,
                'can_delete' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 8. Link existing users to groups according to their role
        DB::table('users')->where('role', 'superadmin')->update(['group_id' => $superadminGroupId]);
        DB::table('users')->where('role', 'admin')->update(['group_id' => $adminGroupId]);
        DB::table('users')->where('role', 'operator')->update(['group_id' => $operatorGroupId]);
        DB::table('users')->whereNull('group_id')->update(['group_id' => $operatorGroupId]);

        // Link existing routers to operator group by default so existing routers are accessible
        $existingRouters = DB::table('routers')->pluck('id');
        foreach ($existingRouters as $rId) {
            DB::table('group_router')->insertOrIgnore([
                'group_id' => $operatorGroupId,
                'router_id' => $rId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('group_router')->insertOrIgnore([
                'group_id' => $viewerGroupId,
                'router_id' => $rId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'simulated_group_id')) {
                $table->dropConstrainedForeignId('simulated_group_id');
            }
            if (Schema::hasColumn('users', 'settings')) {
                $table->dropColumn('settings');
            }
            if (Schema::hasColumn('users', 'group_id')) {
                $table->dropConstrainedForeignId('group_id');
            }
        });

        Schema::dropIfExists('group_router');
        Schema::dropIfExists('group_module_permissions');
        Schema::dropIfExists('modules');
        Schema::dropIfExists('groups');
    }
};
