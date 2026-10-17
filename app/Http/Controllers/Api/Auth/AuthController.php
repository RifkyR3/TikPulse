<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
        $user = User::where('email', $email)->first();

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
        $user->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Language preference updated.',
            'data' => [
                'preferred_locale' => $user->preferred_locale,
            ],
        ]);
    }
}
