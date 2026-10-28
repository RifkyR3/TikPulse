<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Infrastructure\Network\Mikrotik\Services\HotspotVoucherService;
use App\Infrastructure\Network\Mikrotik\Services\PppService;
use App\Models\DedicatedUser;
use App\Models\Router;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureActions();
        $this->configureViews();
        $this->configureRateLimiting();
    }

    /**
     * Configure Fortify actions.
     */
    private function configureActions(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::createUsersUsing(CreateNewUser::class);

        Fortify::authenticateUsing(function (Request $request): ?User {
            $login = trim((string) ($request->input('login') ?? $request->input('email') ?? $request->input('username') ?? ''));
            $password = (string) $request->input('password');

            if ($login === '' || $password === '') {
                return null;
            }

            // 1. Check User table
            $user = User::where('email', $login)->orWhere('name', $login)->first();
            if ($user && Hash::check($password, $user->password)) {
                return $user->is_active ? $user : null;
            }

            // 2. Check Voucher
            $voucher = Voucher::where('username', $login)->first();
            if ($voucher && ($voucher->password === $password || Hash::check($password, $voucher->password))) {
                return User::updateOrCreate(
                    ['email' => "hotspot_{$voucher->router_id}_{$voucher->username}@customer.tikpulse.local"],
                    [
                        'name' => $voucher->username,
                        'role' => 'customer',
                        'password' => Hash::make($password),
                        'is_active' => $voucher->status !== 'disabled',
                        'settings' => [
                            'account_type' => 'hotspot_customer',
                            'router_id' => $voucher->router_id,
                            'voucher_id' => $voucher->id,
                            'theme' => 'system',
                            'accent_color' => 'sky',
                            'preferred_locale' => 'id',
                        ],
                    ]
                );
            }

            // 3. Check DedicatedUser
            $dedicated = DedicatedUser::where('name', $login)->first();
            if ($dedicated && ($dedicated->password === $password || Hash::check($password, $dedicated->password))) {
                return User::updateOrCreate(
                    ['email' => "ppp_{$dedicated->router_id}_{$dedicated->name}@customer.tikpulse.local"],
                    [
                        'name' => $dedicated->name,
                        'role' => 'customer',
                        'password' => Hash::make($password),
                        'is_active' => ! $dedicated->disabled,
                        'settings' => [
                            'account_type' => 'ppp_customer',
                            'router_id' => $dedicated->router_id,
                            'dedicated_user_id' => $dedicated->id,
                            'theme' => 'system',
                            'accent_color' => 'sky',
                            'preferred_locale' => 'id',
                        ],
                    ]
                );
            }

            // 4. Live Router Fallback
            $routers = Router::where('status', 'online')->orWhere('id', 2)->get();
            if ($routers->isEmpty()) {
                $routers = Router::all();
            }

            $pppService = app(PppService::class);
            $hotspotService = app(HotspotVoucherService::class);

            foreach ($routers as $router) {
                try {
                    $secrets = $pppService->getSecrets($router, true);
                    $sec = collect($secrets)->firstWhere('name', $login);
                    if ($sec && (string) ($sec['password'] ?? '') === $password) {
                        $ded = DedicatedUser::updateOrCreate(
                            ['router_id' => $router->id, 'name' => $login],
                            [
                                'password' => $password,
                                'service' => $sec['service'] ?? 'pppoe',
                                'profile' => $sec['profile'] ?? 'default',
                                'disabled' => $sec['disabled'] ?? false,
                                'synced_to_router' => true,
                                'comment' => $sec['comment'] ?? null,
                            ]
                        );

                        return User::updateOrCreate(
                            ['email' => "ppp_{$router->id}_{$login}@customer.tikpulse.local"],
                            [
                                'name' => $login,
                                'role' => 'customer',
                                'password' => Hash::make($password),
                                'is_active' => ! ($sec['disabled'] ?? false),
                                'settings' => [
                                    'account_type' => 'ppp_customer',
                                    'router_id' => $router->id,
                                    'dedicated_user_id' => $ded->id,
                                    'theme' => 'system',
                                    'accent_color' => 'sky',
                                    'preferred_locale' => 'id',
                                ],
                            ]
                        );
                    }

                    $hsUsers = $hotspotService->getHotspotUsers($router, true);
                    $hs = collect($hsUsers)->firstWhere('name', $login);
                    if ($hs && (string) ($hs['password'] ?? '') === $password) {
                        $vouch = Voucher::updateOrCreate(
                            ['router_id' => $router->id, 'username' => $login],
                            [
                                'code' => $login,
                                'password' => $password,
                                'status' => ($hs['disabled'] ?? false) ? 'disabled' : 'active',
                                'synced_to_router' => true,
                                'comment' => $hs['comment'] ?? null,
                            ]
                        );

                        return User::updateOrCreate(
                            ['email' => "hotspot_{$router->id}_{$login}@customer.tikpulse.local"],
                            [
                                'name' => $login,
                                'role' => 'customer',
                                'password' => Hash::make($password),
                                'is_active' => ! ($hs['disabled'] ?? false),
                                'settings' => [
                                    'account_type' => 'hotspot_customer',
                                    'router_id' => $router->id,
                                    'voucher_id' => $vouch->id,
                                    'theme' => 'system',
                                    'accent_color' => 'sky',
                                    'preferred_locale' => 'id',
                                ],
                            ]
                        );
                    }
                } catch (\Throwable) {
                    // Ignore router error and continue to next
                }
            }

            return null;
        });
    }

    /**
     * Configure Fortify views.
     */
    private function configureViews(): void
    {
        Fortify::loginView(fn (Request $request) => Inertia::render('auth/login', [
            'canResetPassword' => Features::enabled(Features::resetPasswords()),
            'status' => $request->session()->get('status'),
        ]));

        Fortify::resetPasswordView(fn (Request $request) => Inertia::render('auth/reset-password', [
            'email' => $request->email,
            'token' => $request->route('token'),
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]));

        Fortify::requestPasswordResetLinkView(fn (Request $request) => Inertia::render('auth/forgot-password', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::verifyEmailView(fn (Request $request) => Inertia::render('auth/verify-email', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::registerView(fn () => Inertia::render('auth/register', [
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]));

        Fortify::twoFactorChallengeView(fn () => Inertia::render('auth/two-factor-challenge'));

        Fortify::confirmPasswordView(fn () => Inertia::render('auth/confirm-password'));
    }

    /**
     * Configure rate limiting.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('passkeys', function (Request $request) {
            return Limit::perMinute(10)->by(
                ($request->input('credential.id') ?: $request->session()->getId()).'|'.$request->ip(),
            );
        });
    }
}
