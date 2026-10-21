<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\Group;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Handle user authentication via Laravel Sanctum.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $request->ensureIsNotRateLimited();

        $email = (string) $request->input('email');
        $password = (string) $request->input('password');

        /** @var User|null $user */
        $user = User::with(['group', 'group.permissions', 'group.routers'])->where('email', $email)->first();

        if (! $user || ! Hash::check($password, $user->password)) {
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
                'message' => 'Your account has been deactivated. Please contact the administrator.',
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
            'message' => 'Authentication successful.',
            'data' => [
                'user' => new UserResource($user),
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

        if ($user) {
            $user->currentAccessToken()?->delete();
        }

        if ($request->hasSession()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Successfully logged out.',
        ])->withoutCookie('tikpulse_token');
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
            'message' => 'Language preference updated.',
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
            'message' => 'Settings saved successfully.',
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
                'message' => 'Only Super Administrators can switch roles.',
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
            ? 'Switched role simulation to group.'
            : 'Reset role to default Super Administrator.';

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
                'message' => 'Only Super Administrators can switch roles.',
            ], 403);
        }

        $user->simulated_group_id = null;
        $user->save();
        $user->load(['group', 'group.permissions', 'group.routers']);

        return response()->json([
            'status' => 'success',
            'message' => 'Simulation cleared. Reverted to Super Administrator.',
            'data' => [
                'user' => new UserResource($user),
            ],
        ]);
    }
}
