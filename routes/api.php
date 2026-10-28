<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Auth\EmailVerificationController;
use App\Http\Controllers\Api\Customer\CustomerStatusController;
use App\Http\Controllers\Api\Hotspot\ActiveUserController;
use App\Http\Controllers\Api\Hotspot\HotspotProfileController;
use App\Http\Controllers\Api\Hotspot\HotspotUserController;
use App\Http\Controllers\Api\Hotspot\VoucherController;
use App\Http\Controllers\Api\Ppp\DedicatedUserController;
use App\Http\Controllers\Api\Ppp\PppActiveController;
use App\Http\Controllers\Api\Ppp\PppProfileController;
use App\Http\Controllers\Api\Router\RouterController;
use App\Http\Controllers\Api\SafeMode\SafeModeController;
use App\Http\Controllers\Api\User\GroupController;
use App\Http\Controllers\Api\User\ModuleController;
use App\Http\Controllers\Api\User\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| TikPulse API Routes - V1
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function (): void {
    // --------------------------------------------------------------------------
    // Public Auth Routes (Rate Limited)
    // --------------------------------------------------------------------------
    Route::prefix('auth')->group(function (): void {
        Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    });

    // --------------------------------------------------------------------------
    // Protected API Routes (Requires Laravel Sanctum)
    // --------------------------------------------------------------------------
    Route::middleware('auth:sanctum')->group(function (): void {
        // Authenticated User Context & Preferences
        Route::prefix('auth')->group(function (): void {
            Route::post('logout', [AuthController::class, 'logout']);
            Route::get('me', [AuthController::class, 'me']);
            Route::post('locale', [AuthController::class, 'updateLocale']);
            Route::post('settings', [AuthController::class, 'updateSettings']);
            Route::post('switch-role', [AuthController::class, 'switchRole']);
            Route::post('reset-role', [AuthController::class, 'resetRole']);
        });

        // Email Verification (Sanctum)
        Route::prefix('email')->group(function (): void {
            Route::post('verification-notification', [EmailVerificationController::class, 'resend'])
                ->middleware('throttle:6,1');
            Route::post('verify-code', [EmailVerificationController::class, 'verifyCode'])
                ->middleware('throttle:10,1');
        });

        // ----------------------------------------------------------------------
        // Customer Self-Service & Real-time Status Portal
        // ----------------------------------------------------------------------
        Route::prefix('customer')->group(function (): void {
            Route::get('status', [CustomerStatusController::class, 'status']);
            Route::put('profile', [CustomerStatusController::class, 'updateProfile']);
            Route::post('disconnect', [CustomerStatusController::class, 'disconnect']);
            Route::post('logout', [CustomerStatusController::class, 'logout']);
        });

        // ----------------------------------------------------------------------
        // Module & RBAC Metadata
        // ----------------------------------------------------------------------
        Route::get('modules', [ModuleController::class, 'index']);

        // ----------------------------------------------------------------------
        // User & Group Management (ACL Protected)
        // ----------------------------------------------------------------------
        Route::apiResource('groups', GroupController::class)
            ->middleware('module.access:groups,read');

        Route::apiResource('users', UserController::class)
            ->middleware('module.access:users,read');
        Route::post('users/{user}/reset-password', [UserController::class, 'resetPassword'])
            ->middleware('module.access:users,update');

        // ----------------------------------------------------------------------
        // Multi-Router Management
        // ----------------------------------------------------------------------
        Route::apiResource('routers', RouterController::class)
            ->middleware('module.access:routers,read');

        Route::post('routers/{router}/test-connection', [RouterController::class, 'testConnection'])
            ->middleware(['router.context:true', 'module.access:routers,read']);

        Route::get('routers/{router}/telemetry', [RouterController::class, 'telemetry'])
            ->middleware(['router.context:true', 'module.access:routers,read']);

        // Destructive router actions (Protected by EnsureSafeModeCompliance & router scoping)
        Route::post('routers/{router}/reboot', [RouterController::class, 'reboot'])
            ->middleware(['router.context:true', 'module.access:routers,update', 'safe.mode:system_reboot']);

        // ----------------------------------------------------------------------
        // Safe Mode Guardrail Controls
        // ----------------------------------------------------------------------
        Route::get('safe-mode/audit-logs', [SafeModeController::class, 'allAuditLogs'])
            ->middleware('module.access:safe_mode_logs,read');

        Route::prefix('routers/{router}/safe-mode')->middleware(['router.context:true', 'module.access:safe_mode_logs,read'])->group(function (): void {
            Route::get('status', [SafeModeController::class, 'status']);
            Route::post('unlock', [SafeModeController::class, 'unlock']);
            Route::post('lock', [SafeModeController::class, 'lock']);
            Route::post('toggle', [SafeModeController::class, 'toggle']);
            Route::get('audit-logs', [SafeModeController::class, 'auditLogs']);
        });

        // ----------------------------------------------------------------------
        // Hotspot Profiles, Vouchers & Real-time Operations
        // Scoped by Router Context & Module Permission
        // ----------------------------------------------------------------------
        Route::prefix('routers/{router}')->middleware('router.context:true')->group(function (): void {
            // Hotspot Bandwidth Profiles
            Route::middleware('module.access:hotspot_vouchers,read')->group(function (): void {
                Route::get('profiles', [HotspotProfileController::class, 'index']);
                Route::get('vouchers', [VoucherController::class, 'index']);
                Route::get('vouchers/batches', [VoucherController::class, 'batches']);
                Route::get('vouchers/batches/{batch}', [VoucherController::class, 'batchDetail']);
                Route::get('hotspot/active', [ActiveUserController::class, 'index']);
                Route::get('hotspot/users', [HotspotUserController::class, 'index']);
                Route::get('traffic', [ActiveUserController::class, 'traffic']);
                Route::get('interfaces', [ActiveUserController::class, 'interfaces']);
            });

            Route::middleware('module.access:hotspot_vouchers,write')->group(function (): void {
                Route::post('profiles', [HotspotProfileController::class, 'store']);
                Route::post('profiles/sync', [HotspotProfileController::class, 'sync']);
                Route::post('vouchers/generate', [VoucherController::class, 'generate']);
                Route::post('vouchers/mark-printed', [VoucherController::class, 'markPrinted']);
                Route::post('hotspot/users', [HotspotUserController::class, 'store']);
                Route::post('hotspot/users/sync', [HotspotUserController::class, 'sync']);
            });

            Route::middleware('module.access:hotspot_vouchers,update')->group(function (): void {
                Route::put('profiles/{profile}', [HotspotProfileController::class, 'update']);
                Route::post('hotspot/active/{sessionId}/kick', [ActiveUserController::class, 'kick']);
            });

            Route::middleware('module.access:hotspot_vouchers,delete')->group(function (): void {
                Route::delete('profiles/{profile}', [HotspotProfileController::class, 'destroy'])
                    ->middleware('safe.mode:bulk_delete_profiles');
                Route::post('vouchers/bulk-delete', [VoucherController::class, 'bulkDelete'])
                    ->middleware('safe.mode:bulk_delete_vouchers');
                Route::delete('hotspot/users/{username}', [HotspotUserController::class, 'destroy'])
                    ->middleware('safe.mode:bulk_delete_vouchers');
            });

            // ------------------------------------------------------------------
            // Dedicated User Management (MikroTik PPP Secrets & Profiles)
            // ------------------------------------------------------------------
            Route::prefix('ppp')->group(function (): void {
                Route::middleware('module.access:ppp_subscribers,read')->group(function (): void {
                    Route::get('profiles', [PppProfileController::class, 'index']);
                    Route::get('pools', [PppProfileController::class, 'pools']);
                    Route::get('secrets', [DedicatedUserController::class, 'index']);
                    Route::get('active', [PppActiveController::class, 'index']);
                });

                Route::middleware('module.access:ppp_subscribers,write')->group(function (): void {
                    Route::post('profiles', [PppProfileController::class, 'store']);
                    Route::post('profiles/sync', [PppProfileController::class, 'sync']);
                    Route::post('secrets', [DedicatedUserController::class, 'store']);
                    Route::post('secrets/sync', [DedicatedUserController::class, 'sync']);
                });

                Route::middleware('module.access:ppp_subscribers,update')->group(function (): void {
                    Route::put('profiles/{profile}', [PppProfileController::class, 'update']);
                    Route::put('secrets/{dedicatedUser}', [DedicatedUserController::class, 'update']);
                    Route::post('secrets/{dedicatedUser}/toggle', [DedicatedUserController::class, 'toggle']);
                    Route::post('active/{sessionId}/kick', [PppActiveController::class, 'kick']);
                });

                Route::middleware('module.access:ppp_subscribers,delete')->group(function (): void {
                    Route::delete('profiles/{profile}', [PppProfileController::class, 'destroy'])
                        ->middleware('safe.mode:bulk_delete_ppp_profiles');
                    Route::delete('secrets/{dedicatedUser}', [DedicatedUserController::class, 'destroy'])
                        ->middleware('safe.mode:bulk_delete_ppp_secrets');
                });
            });
        });
    });
});
