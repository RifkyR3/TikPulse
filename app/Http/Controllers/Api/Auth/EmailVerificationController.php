<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class EmailVerificationController extends Controller
{
    /**
     * Resend verification email containing 6-digit code.
     */
    public function resend(Request $request): JsonResponse|RedirectResponse
    {
        /** @var User|null $user */
        $user = $request->user();

        if (! $user) {
            if ($request->wantsJson() || $request->is('api/*')) {
                return response()->json([
                    'status' => 'error',
                    'message' => __('auth.unauthenticated'),
                ], 401);
            }

            return redirect()->route('login');
        }

        $user->refresh();
        $locale = $user->preferred_locale ?? config('app.locale', 'en');

        $requestedEmail = $request->input('email');
        if (is_string($requestedEmail) && trim($requestedEmail) !== '') {
            $newEmail = strtolower(trim($requestedEmail));
            if ($newEmail !== strtolower(trim((string) $user->email))) {
                $request->validate([
                    'email' => ['email', 'max:255', 'unique:users,email,'.$user->id],
                ]);
                $user->email = $newEmail;
                $user->email_verified_at = null;
                $user->save();
            }
        }

        if ($user->hasVerifiedEmail()) {
            if ($request->wantsJson() || $request->is('api/*')) {
                return response()->json([
                    'status' => 'success',
                    'message' => __('verification.already_verified', locale: $locale),
                    'data' => [
                        'user' => new UserResource($user),
                        'is_email_verified' => true,
                    ],
                ]);
            }

            return redirect()->intended(
                $user->role === 'customer' ? '/status' : '/dashboard'
            );
        }

        $user->sendEmailVerificationNotification();

        if ($request->wantsJson() || $request->is('api/*')) {
            return response()->json([
                'status' => 'success',
                'message' => __('verification.code_sent', locale: $locale),
            ]);
        }

        return back()->with('status', 'verification-link-sent');
    }

    /**
     * Verify email address using 6-digit code.
     */
    public function verifyCode(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'size:6'],
        ]);

        /** @var User|null $user */
        $user = $request->user();

        if (! $user) {
            if ($request->wantsJson() || $request->is('api/*')) {
                return response()->json([
                    'status' => 'error',
                    'message' => __('auth.unauthenticated'),
                ], 401);
            }

            return redirect()->route('login');
        }

        $user->refresh();
        $locale = $user->preferred_locale ?? config('app.locale', 'en');

        if ($user->hasVerifiedEmail()) {
            if ($request->wantsJson() || $request->is('api/*')) {
                return response()->json([
                    'status' => 'success',
                    'message' => __('verification.already_verified_subsequent', locale: $locale),
                    'data' => [
                        'user' => new UserResource($user),
                        'is_email_verified' => true,
                    ],
                ]);
            }

            return redirect()->intended(
                $user->role === 'customer' ? '/status' : '/dashboard'
            );
        }

        $inputCode = trim((string) $validated['code']);
        /** @var array{code: string, email: string}|null $cached */
        $cached = Cache::get("email_verify_code_{$user->id}");

        if (! $cached || ! is_array($cached)) {
            $msg = __('verification.code_expired', locale: $locale);

            if ($request->wantsJson() || $request->is('api/*')) {
                return response()->json([
                    'status' => 'error',
                    'message' => $msg,
                ], 422);
            }

            return back()->withErrors(['code' => $msg]);
        }

        if ($cached['code'] !== $inputCode || $cached['email'] !== $user->email) {
            $msg = __('verification.invalid_code', locale: $locale);

            if ($request->wantsJson() || $request->is('api/*')) {
                return response()->json([
                    'status' => 'error',
                    'message' => $msg,
                ], 422);
            }

            return back()->withErrors(['code' => $msg]);
        }

        Cache::forget("email_verify_code_{$user->id}");

        $user->markEmailAsVerified();
        event(new Verified($user));

        if ($request->wantsJson() || $request->is('api/*')) {
            return response()->json([
                'status' => 'success',
                'message' => __('verification.verified_success', locale: $locale),
                'data' => [
                    'user' => new UserResource($user->fresh()),
                    'is_email_verified' => true,
                ],
            ]);
        }

        return redirect()->intended(
            $user->role === 'customer' ? '/status' : '/dashboard'
        )->with('status', 'verification-success');
    }
}
