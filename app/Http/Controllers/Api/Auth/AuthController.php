<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Infrastructure\Network\Mikrotik\Services\HotspotVoucherService;
use App\Infrastructure\Network\Mikrotik\Services\PppService;
use App\Models\DedicatedUser;
use App\Models\Group;
use App\Models\Router;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    public function __construct(
        protected ?PppService $pppService = null,
        protected ?HotspotVoucherService $hotspotService = null,
    ) {
        $this->pppService ??= app(PppService::class);
        $this->hotspotService ??= app(HotspotVoucherService::class);
    }

    /**
     * Handle user authentication via Laravel Sanctum.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $request->ensureIsNotRateLimited();

        $identifier = $request->getIdentifier();
        $password = (string) $request->input('password');

        /** @var User|null $user */
        $user = null;

        // 1. Try finding in users table by email or name
        if (str_contains($identifier, '@')) {
            $user = User::with(['group', 'group.permissions', 'group.routers'])->where('email', $identifier)->first();
        } else {
            $user = User::with(['group', 'group.permissions', 'group.routers'])
                ->where('name', $identifier)
                ->orWhere('email', $identifier)
                ->first();
        }

        $accountType = 'admin';

        if ($user) {
            if (! Hash::check($password, $user->password)) {
                $user = null;
            } else {
                $accountType = $user->role === 'customer'
                    ? ($user->settings['account_type'] ?? 'customer')
                    : 'admin';
            }
        }

        // 2. If not found or password didn't match User, check Voucher (Hotspot customer)
        if (! $user) {
            $voucher = Voucher::where('username', $identifier)->first();
            if ($voucher && ($voucher->password === $password || Hash::check($password, $voucher->password))) {
                $user = User::updateOrCreate(
                    [
                        'email' => "hotspot_{$voucher->router_id}_{$voucher->username}@customer.tikpulse.local",
                    ],
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
                $accountType = 'hotspot_customer';
            }
        }

        // 3. If still not found, check DedicatedUser (PPP subscriber)
        if (! $user) {
            $dedicated = DedicatedUser::where('name', $identifier)->first();
            if ($dedicated && ($dedicated->password === $password || Hash::check($password, $dedicated->password))) {
                $user = User::updateOrCreate(
                    [
                        'email' => "ppp_{$dedicated->router_id}_{$dedicated->name}@customer.tikpulse.local",
                    ],
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
                $accountType = 'ppp_customer';
            }
        }

        // 4. Live Router Query Fallback (for unsynced PPP secrets or Hotspot users directly on MikroTik)
        if (! $user && ! str_contains($identifier, '@')) {
            $routers = Router::where('status', 'online')->orWhere('id', 2)->get();
            if ($routers->isEmpty()) {
                $routers = Router::all();
            }

            foreach ($routers as $router) {
                try {
                    $secrets = $this->pppService->getSecrets($router, true);
                    $sec = collect($secrets)->firstWhere('name', $identifier);
                    if ($sec && (string) ($sec['password'] ?? '') === $password) {
                        $dedicated = DedicatedUser::updateOrCreate(
                            ['router_id' => $router->id, 'name' => $identifier],
                            [
                                'password' => $password,
                                'service' => $sec['service'] ?? 'pppoe',
                                'profile' => $sec['profile'] ?? 'default',
                                'disabled' => $sec['disabled'] ?? false,
                                'synced_to_router' => true,
                                'comment' => $sec['comment'] ?? null,
                            ]
                        );

                        $user = User::updateOrCreate(
                            ['email' => "ppp_{$router->id}_{$identifier}@customer.tikpulse.local"],
                            [
                                'name' => $identifier,
                                'role' => 'customer',
                                'password' => Hash::make($password),
                                'is_active' => ! ($sec['disabled'] ?? false),
                                'settings' => [
                                    'account_type' => 'ppp_customer',
                                    'router_id' => $router->id,
                                    'dedicated_user_id' => $dedicated->id,
                                    'theme' => 'system',
                                    'accent_color' => 'sky',
                                    'preferred_locale' => 'id',
                                ],
                            ]
                        );
                        $accountType = 'ppp_customer';
                        break;
                    }

                    $hsUsers = $this->hotspotService->getHotspotUsers($router, true);
                    $hs = collect($hsUsers)->firstWhere('name', $identifier);
                    if ($hs && (string) ($hs['password'] ?? '') === $password) {
                        $voucher = Voucher::updateOrCreate(
                            ['router_id' => $router->id, 'username' => $identifier],
                            [
                                'code' => $identifier,
                                'password' => $password,
                                'status' => ($hs['disabled'] ?? false) ? 'disabled' : 'active',
                                'synced_to_router' => true,
                                'comment' => $hs['comment'] ?? null,
                            ]
                        );

                        $user = User::updateOrCreate(
                            ['email' => "hotspot_{$router->id}_{$identifier}@customer.tikpulse.local"],
                            [
                                'name' => $identifier,
                                'role' => 'customer',
                                'password' => Hash::make($password),
                                'is_active' => ! ($hs['disabled'] ?? false),
                                'settings' => [
                                    'account_type' => 'hotspot_customer',
                                    'router_id' => $router->id,
                                    'voucher_id' => $voucher->id,
                                    'theme' => 'system',
                                    'accent_color' => 'sky',
                                    'preferred_locale' => 'id',
                                ],
                            ]
                        );
                        $accountType = 'hotspot_customer';
                        break;
                    }
                } catch (\Throwable) {
                    // Non-fatal router connection error during credentials check
                }
            }
        }

        if (! $user) {
            $request->hitRateLimiter();

            throw ValidationException::withMessages([
                'email' => [trans('auth.failed')],
            ]);
        }

        if (! $user->is_active) {
            $request->hitRateLimiter();

            return response()->json([
                'status' => 'error',
                'code' => 'ACCOUNT_DEACTIVATED',
                'message' => __('auth.account_deactivated'),
            ], 403);
        }

        $request->clearRateLimiter();

        $remember = $request->boolean('remember');
        $lifetimeDays = $remember ? 30 : 1;

        if ($request->hasSession()) {
            Auth::login($user, $remember);
        }

        // Create Sanctum PlainTextToken
        $token = $user->createToken('tikpulse_auth_token', ['*'], now()->addDays($lifetimeDays))->plainTextToken;

        return response()->json([
            'status' => 'success',
            'message' => __('auth.login_success'),
            'data' => [
                'user' => new UserResource($user),
                'account_type' => $accountType,
                'token' => $token,
                'token_type' => 'Bearer',
                'remember' => $remember,
            ],
        ])->cookie(
            'tikpulse_token',
            $token,
            60 * 24 * $lifetimeDays,
            '/',
            null,
            $request->isSecure(),
            true // HttpOnly
        );
    }

    /**
     * Terminate user session and revoke token.
     */
    public function logout(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();

        $tokenString = $request->bearerToken() ?? $request->cookie('tikpulse_token');
        if (is_string($tokenString) && $tokenString !== '') {
            PersonalAccessToken::findToken($tokenString)?->delete();
        }

        if ($user) {
            $user->currentAccessToken()?->delete();
            if ($user->role === 'customer') {
                $user->tokens()->where('name', 'tikpulse_auth_token')->delete();
            }
        }

        Auth::guard('web')->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json([
            'status' => 'success',
            'message' => __('auth.token_revoked'),
        ])->withCookie(cookie()->forget('tikpulse_token', '/'))
            ->withCookie(cookie()->forget('tikpulse_session', '/'));
    }

    /**
     * Get current authenticated user profile.
     */
    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->load(['group', 'group.permissions', 'group.routers']);

        return response()->json([
            'status' => 'success',
            'data' => [
                'user' => new UserResource($user),
            ],
        ]);
    }

    /**
     * Update user's preferred language (persisted across sessions).
     */
    public function updateLocale(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'locale' => ['required', 'string', 'in:en,id'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $user->preferred_locale = $validated['locale'];

        $settings = $user->settings ?? [];
        $settings['preferred_locale'] = $validated['locale'];
        $user->settings = $settings;

        $user->save();

        return response()->json([
            'status' => 'success',
            'message' => __('auth.locale_updated'),
            'data' => [
                'preferred_locale' => $user->preferred_locale,
                'settings' => $user->settings,
            ],
        ]);
    }

    /**
     * Update user appearance, colors, and application preferences.
     */
    public function updateSettings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'theme' => ['nullable', 'string', 'in:light,dark,system'],
            'accent_color' => ['nullable', 'string', 'in:sky,indigo,emerald,violet,amber,rose'],
            'preferred_locale' => ['nullable', 'string', 'in:en,id'],
            'refresh_interval' => ['nullable', 'integer', 'in:0,5,10,15,30,60'],
            'table_compact_mode' => ['nullable', 'boolean'],
            'settings' => ['nullable', 'array'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $currentSettings = $user->settings ?? [
            'theme' => 'system',
            'accent_color' => 'sky',
            'refresh_interval' => 15,
            'table_compact_mode' => false,
        ];

        if (isset($validated['settings']) && is_array($validated['settings'])) {
            $currentSettings = array_merge($currentSettings, $validated['settings']);
        }

        foreach (['theme', 'accent_color', 'refresh_interval', 'table_compact_mode'] as $key) {
            if (isset($validated[$key])) {
                $currentSettings[$key] = $validated[$key];
            }
        }

        if (isset($validated['preferred_locale'])) {
            $user->preferred_locale = $validated['preferred_locale'];
            $currentSettings['preferred_locale'] = $validated['preferred_locale'];
        }

        $user->settings = $currentSettings;
        $user->save();
        $user->load(['group', 'group.permissions', 'group.routers']);

        return response()->json([
            'status' => 'success',
            'message' => __('auth.preferences_updated'),
            'data' => [
                'user' => new UserResource($user),
            ],
        ]);
    }

    /**
     * Superadmin switch role / group context simulation.
     */
    public function switchRole(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $user->isSuperAdmin()) {
            return response()->json([
                'status' => 'error',
                'code' => 'SUPERADMIN_ONLY',
                'message' => __('users.reset_admin_forbidden'),
            ], 403);
        }

        $validated = $request->validate([
            'group_id' => ['nullable', 'integer', 'exists:groups,id'],
        ]);

        $groupId = $validated['group_id'] ?? null;
        $user->simulated_group_id = $groupId ? (int) $groupId : null;
        $user->save();
        $user->load(['group', 'group.permissions', 'group.routers']);

        $message = $user->simulated_group_id
            ? __('auth.switched_role', ['group' => $user->group?->name ?? 'group'])
            : __('auth.reverted_role');

        return response()->json([
            'status' => 'success',
            'message' => $message,
            'data' => [
                'user' => new UserResource($user),
            ],
        ]);
    }

    /**
     * Reset role simulation back to Superadmin.
     */
    public function resetRole(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $user->isSuperAdmin()) {
            return response()->json([
                'status' => 'error',
                'code' => 'SUPERADMIN_ONLY',
                'message' => __('users.reset_admin_forbidden'),
            ], 403);
        }

        $user->simulated_group_id = null;
        $user->save();
        $user->load(['group', 'group.permissions', 'group.routers']);

        return response()->json([
            'status' => 'success',
            'message' => __('auth.reverted_role'),
            'data' => [
                'user' => new UserResource($user),
            ],
        ]);
    }
}
