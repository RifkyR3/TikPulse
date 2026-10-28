<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Customer;

use App\Application\Customer\Services\CustomerCredentialSyncService;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Infrastructure\Network\Mikrotik\Services\HotspotVoucherService;
use App\Infrastructure\Network\Mikrotik\Services\PppService;
use App\Models\DedicatedUser;
use App\Models\Router;
use App\Models\User;
use App\Models\Voucher;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class CustomerStatusController extends Controller
{
    public function __construct(
        protected HotspotVoucherService $hotspotService,
        protected PppService $pppService,
        protected CustomerCredentialSyncService $credentialSyncService
    ) {}

    /**
     * Get live connection status, active session metrics, and quota details for authenticated customer.
     */
    public function status(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->refresh();

        $accountType = $this->credentialSyncService->resolveAccountType($user);
        $settings = $user->settings ?? [];
        $routerId = isset($settings['router_id']) ? (int) $settings['router_id'] : null;

        if ($accountType === 'hotspot_customer') {
            return $this->getHotspotCustomerStatus($user, $routerId);
        }

        if ($accountType === 'ppp_customer') {
            return $this->getPppCustomerStatus($user, $routerId);
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'account_type' => 'admin',
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'message' => __('customer.admin_context'),
            ],
        ]);
    }

    /**
     * Disconnect/reset current customer active session on MikroTik router.
     */
    public function disconnect(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $accountType = $this->credentialSyncService->resolveAccountType($user);
        $router = $this->credentialSyncService->resolveRouter($user, $accountType);

        if (! $router) {
            return response()->json([
                'status' => 'error',
                'message' => __('customer.no_router'),
            ], 400);
        }

        try {
            if ($accountType === 'hotspot_customer') {
                $sessions = $this->hotspotService->getActiveUsers($router, true);
                $mySession = collect($sessions)->firstWhere('user', $user->name);
                if ($mySession && isset($mySession['.id'])) {
                    $this->hotspotService->kickActiveUser($router, $mySession['.id']);

                    return response()->json([
                        'status' => 'success',
                        'message' => __('customer.hotspot_disconnected'),
                    ]);
                }

                return response()->json([
                    'status' => 'error',
                    'message' => __('customer.no_hotspot_session'),
                ], 404);
            }

            if ($accountType === 'ppp_customer') {
                $connections = $this->pppService->getActiveConnections($router, true);
                $myConn = collect($connections)->firstWhere('name', $user->name);
                if ($myConn && isset($myConn['id'])) {
                    $this->pppService->kickActiveConnection($router, $myConn['id']);

                    return response()->json([
                        'status' => 'success',
                        'message' => __('customer.ppp_disconnected'),
                    ]);
                }

                return response()->json([
                    'status' => 'error',
                    'message' => __('customer.no_ppp_connection'),
                ], 404);
            }

            return response()->json([
                'status' => 'error',
                'message' => __('customer.unsupported_type'),
            ], 400);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => __('customer.disconnect_failed', ['error' => $e->getMessage()]),
            ], 500);
        }
    }

    /**
     * Terminate customer authentication session.
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
            'message' => __('customer.logout_success'),
        ])->withCookie(cookie()->forget('tikpulse_token', '/'))
            ->withCookie(cookie()->forget('tikpulse_session', '/'));
    }

    /**
     * Update customer subscriber profile, contact info, appearance, and credentials.
     */
    public function updateProfile(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->refresh();

        $accountType = $this->credentialSyncService->resolveAccountType($user);
        $router = $this->credentialSyncService->resolveRouter($user, $accountType);
        $routerId = $router?->id;

        $validated = $request->validate([
            'email' => ['nullable', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'customer_name' => ['nullable', 'string', 'max:120'],
            'customer_phone' => ['nullable', 'string', 'max:40'],
            'customer_address' => ['nullable', 'string', 'max:255'],
            'current_password' => ['nullable', 'string', 'required_with:new_password'],
            'new_password' => ['nullable', 'string', 'min:6', 'confirmed'],
            'preferred_locale' => ['nullable', 'string', 'in:en,id'],
            'theme' => ['nullable', 'string', 'in:light,dark,system'],
            'accent_color' => ['nullable', 'string', 'in:sky,indigo,emerald,violet,amber,rose'],
        ]);

        // 1. Email update if requested
        if (array_key_exists('email', $validated) && ! empty($validated['email'])) {
            $newEmail = strtolower(trim((string) $validated['email']));
            if ($newEmail !== strtolower(trim((string) $user->email))) {
                $user->email = $newEmail;
                $user->email_verified_at = null;
                try {
                    $user->sendEmailVerificationNotification();
                } catch (\Throwable $e) {
                    Log::warning("Failed to dispatch verification email to {$newEmail}: {$e->getMessage()}");
                }
            }
        }

        // 2. Password update if requested
        if (! empty($validated['new_password'])) {
            $currentPass = (string) $validated['current_password'];
            $newPass = (string) $validated['new_password'];

            // Verify current password against User model or database record
            $isCorrect = Hash::check($currentPass, $user->password);
            if (! $isCorrect) {
                if ($accountType === 'ppp_customer') {
                    $ded = DedicatedUser::where('name', $user->name)
                        ->when($routerId, fn ($q) => $q->where('router_id', $routerId))
                        ->first();
                    $isCorrect = $ded && $ded->password === $currentPass;
                } elseif ($accountType === 'hotspot_customer') {
                    $vouch = Voucher::where(function ($q) use ($user): void {
                        $q->where('username', $user->name)->orWhere('code', $user->name);
                    })
                        ->when($routerId, fn ($q) => $q->where('router_id', $routerId))
                        ->first();
                    $isCorrect = $vouch && $vouch->password === $currentPass;
                }
            }

            if (! $isCorrect) {
                throw ValidationException::withMessages([
                    'current_password' => [trans('auth.password')],
                ]);
            }

            // Web portal password only: do not modify password on the MikroTik router or router database entities
            $user->password = Hash::make($newPass);
            $user->tokens()->delete();
        }

        // 2. Update contact details for PPPoE subscribers
        if ($accountType === 'ppp_customer') {
            $dedicatedUpdates = [];
            if (array_key_exists('customer_name', $validated) && $validated['customer_name'] !== null) {
                $dedicatedUpdates['customer_name'] = $validated['customer_name'];
            }
            if (array_key_exists('customer_phone', $validated)) {
                $dedicatedUpdates['customer_phone'] = $validated['customer_phone'];
            }
            if (array_key_exists('customer_address', $validated)) {
                $dedicatedUpdates['customer_address'] = $validated['customer_address'];
            }

            if (! empty($dedicatedUpdates)) {
                DedicatedUser::where('router_id', $routerId)
                    ->where('name', $user->name)
                    ->update($dedicatedUpdates);
            }
        }

        // 3. Update User Preferences & Settings
        $currentSettings = $user->settings ?? [];
        if (! empty($validated['theme'])) {
            $currentSettings['theme'] = $validated['theme'];
        }
        if (! empty($validated['accent_color'])) {
            $currentSettings['accent_color'] = $validated['accent_color'];
        }
        if (! empty($validated['preferred_locale'])) {
            $user->preferred_locale = $validated['preferred_locale'];
            $currentSettings['preferred_locale'] = $validated['preferred_locale'];
        }

        $user->settings = $currentSettings;
        $user->save();

        return response()->json([
            'status' => 'success',
            'message' => __('customer.profile_updated'),
            'data' => [
                'user' => new UserResource($user),
            ],
        ]);
    }

    protected function getHotspotCustomerStatus(User $user, ?int $routerId): JsonResponse
    {
        $voucher = null;
        if ($routerId) {
            $voucher = Voucher::with(['router', 'profile'])
                ->where('router_id', $routerId)
                ->where('username', $user->name)
                ->first();
        }

        if (! $voucher) {
            $voucher = Voucher::with(['router', 'profile'])
                ->where('username', $user->name)
                ->first();
        }

        $router = $voucher?->router ?? ($routerId ? Router::find($routerId) : null);
        $activeSession = null;
        $isOnline = false;

        if ($router) {
            try {
                $sessions = $this->hotspotService->getActiveUsers($router);
                $found = collect($sessions)->firstWhere('user', $user->name);
                if ($found) {
                    $isOnline = true;
                    $bIn = isset($found['bytes-in']) ? (int) $found['bytes-in'] : 0;
                    $bOut = isset($found['bytes-out']) ? (int) $found['bytes-out'] : 0;
                    $activeSession = [
                        'id' => $found['.id'] ?? null,
                        'ip_address' => $found['address'] ?? null,
                        'mac_address' => $found['mac-address'] ?? null,
                        'uptime' => $found['uptime'] ?? '0s',
                        'time_left' => $found['session-time-left'] ?? null,
                        'idle_time' => $found['idle-time'] ?? null,
                        'bytes_in' => $bIn,
                        'bytes_out' => $bOut,
                        'bytes_total' => $bIn + $bOut,
                        'bytes_in_formatted' => $this->formatBytes($bIn),
                        'bytes_out_formatted' => $this->formatBytes($bOut),
                        'bytes_total_formatted' => $this->formatBytes($bIn + $bOut),
                        'login_by' => $found['login-by'] ?? 'http-pap',
                    ];
                }
            } catch (Exception) {
                // Router query failure handled gracefully
            }
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'account_type' => 'hotspot_customer',
                'username' => $user->name,
                'name' => $user->name,
                'email' => $user->email,
                'is_email_verified' => $user->hasVerifiedEmail(),
                'email_verified_at' => $user->email_verified_at?->toIso8601String(),
                'default_password' => $voucher?->password,
                'is_online' => $isOnline,
                'router' => $router ? [
                    'id' => $router->id,
                    'name' => $router->name,
                    'status' => $router->status,
                ] : null,
                'profile' => [
                    'name' => $voucher?->profile?->name ?? 'Default',
                    'rate_limit' => $voucher?->profile?->rate_limit ?? null,
                    'price' => (float) ($voucher?->profile?->selling_price ?? 0),
                    'validity_duration' => $voucher?->profile?->validity_duration,
                    'validity_unit' => $voucher?->profile?->validity_unit,
                ],
                'limits' => [
                    'time_limit' => $voucher?->time_limit,
                    'data_limit_bytes' => $voucher?->data_limit_bytes,
                    'data_limit_formatted' => $voucher?->data_limit_bytes ? $this->formatBytes((int) $voucher->data_limit_bytes) : null,
                ],
                'session' => $activeSession,
                'voucher_status' => $voucher?->status ?? 'available',
                'created_at' => $voucher?->created_at?->toIso8601String(),
            ],
        ]);
    }

    protected function getPppCustomerStatus(User $user, ?int $routerId): JsonResponse
    {
        $dedicated = null;
        if ($routerId) {
            $dedicated = DedicatedUser::with(['router', 'pppProfile'])
                ->where('router_id', $routerId)
                ->where('name', $user->name)
                ->first();
        }

        if (! $dedicated) {
            $dedicated = DedicatedUser::with(['router', 'pppProfile'])
                ->where('name', $user->name)
                ->first();
        }

        $router = $dedicated?->router ?? ($routerId ? Router::find($routerId) : null);
        $activeSession = null;
        $isOnline = false;

        if ($router) {
            try {
                $connections = $this->pppService->getActiveConnections($router);
                $found = collect($connections)->firstWhere('name', $user->name);
                if ($found) {
                    $isOnline = true;
                    $activeSession = [
                        'id' => $found['id'] ?? null,
                        'ip_address' => $found['address'] ?? null,
                        'caller_id' => $found['caller_id'] ?? null,
                        'uptime' => $found['uptime'] ?? '0s',
                        'service' => $found['service'] ?? 'pppoe',
                        'encoding' => $found['encoding'] ?? null,
                    ];
                }
            } catch (Exception) {
                // Router query failure handled gracefully
            }
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'account_type' => 'ppp_customer',
                'username' => $user->name,
                'email' => $user->email,
                'is_email_verified' => $user->hasVerifiedEmail(),
                'email_verified_at' => $user->email_verified_at?->toIso8601String(),
                'customer_name' => $dedicated?->customer_name ?? $user->name,
                'customer_phone' => $dedicated?->customer_phone,
                'customer_address' => $dedicated?->customer_address,
                'default_password' => $dedicated?->password,
                'is_online' => $isOnline,
                'router' => $router ? [
                    'id' => $router->id,
                    'name' => $router->name,
                    'status' => $router->status,
                ] : null,
                'profile' => [
                    'name' => $dedicated?->profile ?? 'default',
                    'rate_limit' => $dedicated?->pppProfile?->rate_limit ?? null,
                    'price' => (float) ($dedicated?->monthly_price ?? 0),
                ],
                'service' => $dedicated?->service ?? 'pppoe',
                'billing_cycle' => $dedicated?->billing_cycle ?? 'monthly',
                'remote_address' => $dedicated?->remote_address,
                'disabled' => (bool) ($dedicated?->disabled ?? false),
                'session' => $activeSession,
                'created_at' => $dedicated?->created_at?->toIso8601String(),
            ],
        ]);
    }

    protected function formatBytes(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = (int) floor(log($bytes, 1024));
        $power = min($power, count($units) - 1);

        return round($bytes / pow(1024, $power), 2).' '.$units[$power];
    }
}
