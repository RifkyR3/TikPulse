<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Router;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin@tikpulse.test'],
            [
                'name' => 'TikPulse Administrator',
                'password' => Hash::make('secret123'),
                'role' => 'superadmin',
                'is_active' => true,
            ]
        );

        $existingRouter = Router::where('name', 'RB750Gr3 - Main Gateway')->first();
        if (! $existingRouter) {
            $router = new Router([
                'name' => 'RB750Gr3 - Main Gateway',
                'host' => '192.168.88.1',
                'api_port' => 8728,
                'protocol' => 'socket',
                'username' => 'admin',
                'is_safe_mode_enabled' => true,
                'use_tls' => false,
                'timeout_seconds' => 5,
                'description' => 'Primary Gateway MikroTik router with hotspot service.',
                'status' => 'unknown',
            ]);
            $router->setPlainPassword('admin123');
            $router->save();
        }
    }
}
