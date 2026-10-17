<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Router\Enums\RouterProtocol;
use App\Domain\Router\Enums\RouterStatus;
use App\Infrastructure\Network\Mikrotik\Drivers\SocketRouterOsDriver;
use App\Infrastructure\Network\Mikrotik\Services\MikrotikConnectionService;
use App\Models\HotspotProfile;
use App\Models\PppProfile;
use App\Models\Router;
use Illuminate\Console\Command;
use Throwable;

class MikrotikProvisionDummyCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mikrotik:setup-dummy
                            {--host=127.0.0.1 : Hostname or IP of the dummy MikroTik instance}
                            {--port=8728 : RouterOS API port}
                            {--username=admin : Administrator username}
                            {--password=admin123 : Desired administrator password}
                            {--timeout=60 : Max seconds to wait for RouterOS to boot and respond}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Provision and initialize Docker dummy MikroTik RouterOS for development and testing';

    public function handle(MikrotikConnectionService $connectionService): int
    {
        /** @var string $host */
        $hostOption = (string) $this->option('host');
        $host = ($hostOption === '127.0.0.1' && env('MIKROTIK_HOST')) ? (string) env('MIKROTIK_HOST') : $hostOption;
        $port = (int) $this->option('port');
        /** @var string $username */
        $username = (string) $this->option('username');
        /** @var string $password */
        $password = (string) $this->option('password');
        $maxWaitSeconds = (int) $this->option('timeout');

        $this->info("Connecting to dummy MikroTik at [{$host}:{$port}]...");

        $driver = new SocketRouterOsDriver;
        $connected = false;
        $authenticatedPassword = $password;
        $startTime = time();

        // 1. Wait for MikroTik API socket to accept connections
        while ((time() - $startTime) < $maxWaitSeconds) {
            // First attempt with desired password
            try {
                $driver->connect($host, $port, $username, $password, 3, false);
                $connected = true;
                $authenticatedPassword = $password;
                $this->info("Successfully authenticated with configured password for '{$username}'.");
                break;
            } catch (Throwable) {
                // Second attempt with empty password (first boot of fresh RouterOS)
                try {
                    $driver->connect($host, $port, $username, '', 3, false);
                    $connected = true;
                    $authenticatedPassword = '';
                    $this->info('Fresh RouterOS detected (blank password). Authenticated successfully.');
                    break;
                } catch (Throwable) {
                    $this->line('Waiting for RouterOS to finish booting... ('.(time() - $startTime).'s elapsed)');
                    sleep(3);
                }
            }
        }

        if (! $connected) {
            $this->error("Could not connect to dummy MikroTik at {$host}:{$port} after {$maxWaitSeconds} seconds.");
            $this->line("Please verify that the Docker container 'mikrotik-dummy' is running: docker compose --profile dev ps");

            return Command::FAILURE;
        }

        // 2. Set admin password if it was blank
        if ($authenticatedPassword === '') {
            $this->info("Setting '{$username}' password to '{$password}'...");
            try {
                // RouterOS v7 user set
                $users = $driver->command('/user/print', ['?name='.$username]);
                $userId = $users[0]['.id'] ?? $username;
                $driver->command('/user/set', [
                    '=.id='.$userId,
                    '=password='.$password,
                ]);
                $this->info("Password for '{$username}' updated successfully.");
            } catch (Throwable $e) {
                $this->warn('Note when setting password: '.$e->getMessage());
            }
        }

        // 3. Set System Identity
        $this->info('Configuring router identity...');
        try {
            $driver->command('/system/identity/set', [
                '=name=TikPulse-Lab-CHR',
            ]);
        } catch (Throwable $e) {
            $this->warn('Identity set skipped: '.$e->getMessage());
        }

        // 4. Ensure API & Webfig services are enabled
        $this->info('Verifying IP services (API 8728, WebFig 80)...');
        try {
            $services = $driver->command('/ip/service/print');
            foreach ($services as $srv) {
                $name = $srv['name'] ?? '';
                $id = $srv['.id'] ?? '';
                if (in_array($name, ['api', 'www', 'winbox'], true) && ($srv['disabled'] ?? 'false') === 'true') {
                    $driver->command('/ip/service/enable', ['=.id='.$id]);
                }
            }
        } catch (Throwable $e) {
            $this->warn('IP services check: '.$e->getMessage());
        }

        // 5. Setup Hotspot User Profiles in RouterOS
        $this->info('Provisioning Hotspot user profiles...');
        $hotspotProfiles = [
            [
                'name' => '1Hour-Regular',
                'shared_users' => 1,
                'rate_limit' => '2M/5M',
                'price' => 3000,
                'selling_price' => 3000,
                'validity_duration' => '1',
                'validity_unit' => 'hours',
            ],
            [
                'name' => '1Day-Unlimited',
                'shared_users' => 1,
                'rate_limit' => '5M/10M',
                'price' => 10000,
                'selling_price' => 10000,
                'validity_duration' => '1',
                'validity_unit' => 'days',
            ],
            [
                'name' => '1Month-VIP',
                'shared_users' => 2,
                'rate_limit' => '10M/20M',
                'price' => 75000,
                'selling_price' => 85000,
                'validity_duration' => '30',
                'validity_unit' => 'days',
            ],
        ];

        foreach ($hotspotProfiles as $prof) {
            try {
                $existing = $driver->command('/ip/hotspot/user/profile/print', ['?name='.$prof['name']]);
                if (empty($existing)) {
                    $driver->command('/ip/hotspot/user/profile/add', [
                        '=name='.$prof['name'],
                        '=shared-users='.$prof['shared_users'],
                        '=rate-limit='.$prof['rate_limit'],
                    ]);
                    $this->line("  + Created Hotspot Profile: {$prof['name']}");
                }
            } catch (Throwable $e) {
                $this->warn("  - Profile {$prof['name']}: ".$e->getMessage());
            }
        }

        // 6. Setup Sample Hotspot Users in RouterOS
        $this->info('Adding sample Hotspot test users...');
        $sampleHotspotUsers = [
            ['name' => 'test01', 'password' => 'test01', 'profile' => '1Hour-Regular'],
            ['name' => 'vip01', 'password' => 'vip01', 'profile' => '1Month-VIP'],
        ];

        foreach ($sampleHotspotUsers as $u) {
            try {
                $existing = $driver->command('/ip/hotspot/user/print', ['?name='.$u['name']]);
                if (empty($existing)) {
                    $driver->command('/ip/hotspot/user/add', [
                        '=name='.$u['name'],
                        '=password='.$u['password'],
                        '=profile='.$u['profile'],
                        '=comment=Created by TikPulse Setup',
                    ]);
                    $this->line("  + Created Hotspot user: {$u['name']}");
                }
            } catch (Throwable) {
                // Ignore if hotspot package is not initialized
            }
        }

        // 7. Setup PPP Profiles & Secrets in RouterOS
        $this->info('Provisioning PPP profiles & secrets...');
        $pppProfiles = [
            [
                'name' => '10Mbps-Dedicated',
                'rate_limit' => '10M/10M',
                'only_one' => true,
                'price' => 250000,
            ],
            [
                'name' => '20Mbps-Dedicated',
                'rate_limit' => '20M/20M',
                'only_one' => true,
                'price' => 450000,
            ],
        ];

        foreach ($pppProfiles as $ppp) {
            try {
                $existing = $driver->command('/ppp/profile/print', ['?name='.$ppp['name']]);
                if (empty($existing)) {
                    $driver->command('/ppp/profile/add', [
                        '=name='.$ppp['name'],
                        '=rate-limit='.$ppp['rate_limit'],
                        '=only-one='.($ppp['only_one'] ? 'yes' : 'no'),
                    ]);
                    $this->line("  + Created PPP Profile: {$ppp['name']}");
                }
            } catch (Throwable $e) {
                $this->warn("  - PPP Profile {$ppp['name']}: ".$e->getMessage());
            }
        }

        // Sample PPP secret
        try {
            $existingSecret = $driver->command('/ppp/secret/print', ['?name=pppoe-demo']);
            if (empty($existingSecret)) {
                $driver->command('/ppp/secret/add', [
                    '=name=pppoe-demo',
                    '=password=demopass123',
                    '=service=pppoe',
                    '=profile=10Mbps-Dedicated',
                    '=comment=TikPulse Demo PPP Subscriber',
                ]);
                $this->line('  + Created PPP secret: pppoe-demo');
            }
        } catch (Throwable) {
            // Ignore if PPP not available
        }

        // 8. Register Router in TikPulse Database
        $this->info('Registering router into TikPulse database...');
        $router = Router::firstOrNew(['name' => 'MikroTik Lab (Docker Dummy)']);
        $router->fill([
            'host' => $host,
            'api_port' => $port,
            'protocol' => RouterProtocol::Socket->value,
            'username' => $username,
            'is_safe_mode_enabled' => true,
            'use_tls' => false,
            'timeout_seconds' => 5,
            'description' => 'Docker CHR Dummy MikroTik for safe development and testing.',
            'status' => RouterStatus::Online->value,
        ]);
        $router->setPlainPassword($password);
        $router->save();

        // 9. Sync Hotspot & PPP Profiles to TikPulse Database
        foreach ($hotspotProfiles as $hp) {
            HotspotProfile::updateOrCreate(
                [
                    'router_id' => $router->id,
                    'name' => $hp['name'],
                ],
                [
                    'shared_users' => $hp['shared_users'],
                    'rate_limit' => $hp['rate_limit'],
                    'price' => $hp['price'],
                    'selling_price' => $hp['selling_price'],
                    'validity_duration' => $hp['validity_duration'],
                    'validity_unit' => $hp['validity_unit'],
                    'is_active' => true,
                ]
            );
        }

        foreach ($pppProfiles as $pp) {
            PppProfile::updateOrCreate(
                [
                    'router_id' => $router->id,
                    'name' => $pp['name'],
                ],
                [
                    'rate_limit' => $pp['rate_limit'],
                    'only_one' => $pp['only_one'],
                    'price' => $pp['price'],
                    'is_active' => true,
                ]
            );
        }

        // 10. Perform live telemetry sync
        try {
            $telemetry = $connectionService->testAndSyncTelemetry($router, true);
            if ($telemetry['success'] ?? false) {
                $this->info('Telemetry synchronized: ROS v'.($router->ros_version ?? '7.x').' ('.($router->board_model ?? 'CHR').')');
            }
        } catch (Throwable $e) {
            $this->warn('Telemetry sync notice: '.$e->getMessage());
        }

        $this->newLine();
        $this->info('========================================================');
        $this->info('  Dummy MikroTik successfully provisioned and ready!   ');
        $this->info('========================================================');
        $this->table(
            ['Attribute', 'Value'],
            [
                ['Router Name in TikPulse', $router->name],
                ['Router ID', (string) $router->id],
                ['Host', $host],
                ['API Port', (string) $port],
                ['Username', $username],
                ['Password', $password],
                ['Safe Mode', 'Enabled (Active)'],
                ['WinBox Access', '127.0.0.1:8291'],
                ['WebFig URL', 'http://localhost:8080'],
            ]
        );

        return Command::SUCCESS;
    }
}
