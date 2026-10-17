<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Hotspot\ActiveUserController;
use App\Http\Controllers\Api\Hotspot\HotspotProfileController;
use App\Http\Controllers\Api\Hotspot\HotspotUserController;
use App\Http\Controllers\Api\Hotspot\VoucherController;
use App\Http\Controllers\Api\Ppp\DedicatedUserController;
use App\Http\Controllers\Api\Ppp\PppActiveController;
use App\Http\Controllers\Api\Ppp\PppProfileController;
use App\Http\Controllers\Api\Router\RouterController;
use App\Http\Controllers\Api\SafeMode\SafeModeController;
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
        // Authenticated User Context
        Route::prefix('auth')->group(function (): void {
            Route::post('logout', [AuthController::class, 'logout']);
            Route::get('me', [AuthController::class, 'me']);
            Route::post('locale', [AuthController::class, 'updateLocale']);
        });

        // ----------------------------------------------------------------------
        // Multi-Router Management
        // ----------------------------------------------------------------------
        Route::apiResource('routers', RouterController::class);
        Route::post('routers/{router}/test-connection', [RouterController::class, 'testConnection']);
        Route::get('routers/{router}/telemetry', [RouterController::class, 'telemetry']);

        // Destructive router actions (Protected by EnsureSafeModeCompliance)
        Route::post('routers/{router}/reboot', [RouterController::class, 'reboot'])
            ->middleware('safe.mode:system_reboot');

        // ----------------------------------------------------------------------
        // Safe Mode Guardrail Controls
        // ----------------------------------------------------------------------
        Route::get('safe-mode/audit-logs', [SafeModeController::class, 'allAuditLogs']);

        Route::prefix('routers/{router}/safe-mode')->group(function (): void {
            Route::get('status', [SafeModeController::class, 'status']);
            Route::post('unlock', [SafeModeController::class, 'unlock']);
            Route::post('lock', [SafeModeController::class, 'lock']);
            Route::post('toggle', [SafeModeController::class, 'toggle']);
            Route::get('audit-logs', [SafeModeController::class, 'auditLogs']);
        });

        // ----------------------------------------------------------------------
        // Hotspot Profiles, Vouchers & Real-time Operations
        // ----------------------------------------------------------------------
        Route::prefix('routers/{router}')->group(function (): void {
            // Hotspot Bandwidth Profiles
            Route::get('profiles', [HotspotProfileController::class, 'index']);
            Route::post('profiles', [HotspotProfileController::class, 'store']);
            Route::post('profiles/sync', [HotspotProfileController::class, 'sync']);
            Route::put('profiles/{profile}', [HotspotProfileController::class, 'update']);
            Route::delete('profiles/{profile}', [HotspotProfileController::class, 'destroy'])
                ->middleware('safe.mode:bulk_delete_profiles');

            // Voucher Generator & Management
            Route::get('vouchers', [VoucherController::class, 'index']);
            Route::post('vouchers/generate', [VoucherController::class, 'generate']);
            Route::get('vouchers/batches', [VoucherController::class, 'batches']);
            Route::get('vouchers/batches/{batch}', [VoucherController::class, 'batchDetail']);
            Route::post('vouchers/mark-printed', [VoucherController::class, 'markPrinted']);
            Route::post('vouchers/bulk-delete', [VoucherController::class, 'bulkDelete'])
                ->middleware('safe.mode:bulk_delete_vouchers');

            // Live Sessions & Bandwidth Traffic
            Route::get('hotspot/active', [ActiveUserController::class, 'index']);
            Route::post('hotspot/active/{sessionId}/kick', [ActiveUserController::class, 'kick']);
            Route::get('hotspot/users', [HotspotUserController::class, 'index']);
            Route::post('hotspot/users', [HotspotUserController::class, 'store']);
            Route::delete('hotspot/users/{username}', [HotspotUserController::class, 'destroy'])
                ->middleware('safe.mode:bulk_delete_vouchers');
            Route::get('traffic', [ActiveUserController::class, 'traffic']);
            Route::get('interfaces', [ActiveUserController::class, 'interfaces']);

            // ------------------------------------------------------------------
            // Dedicated User Management (MikroTik PPP Secrets & Profiles)
            // ------------------------------------------------------------------
            Route::prefix('ppp')->group(function (): void {
                // Profiles
                Route::get('profiles', [PppProfileController::class, 'index']);
                Route::post('profiles', [PppProfileController::class, 'store']);
                Route::post('profiles/sync', [PppProfileController::class, 'sync']);
                Route::put('profiles/{profile}', [PppProfileController::class, 'update']);
                Route::delete('profiles/{profile}', [PppProfileController::class, 'destroy'])
                    ->middleware('safe.mode:bulk_delete_ppp_profiles');
                Route::get('pools', [PppProfileController::class, 'pools']);

                // Dedicated Users (PPP Secrets)
                Route::get('secrets', [DedicatedUserController::class, 'index']);
                Route::post('secrets', [DedicatedUserController::class, 'store']);
                Route::post('secrets/sync', [DedicatedUserController::class, 'sync']);
                Route::put('secrets/{dedicatedUser}', [DedicatedUserController::class, 'update']);
                Route::post('secrets/{dedicatedUser}/toggle', [DedicatedUserController::class, 'toggle']);
                Route::delete('secrets/{dedicatedUser}', [DedicatedUserController::class, 'destroy'])
                    ->middleware('safe.mode:bulk_delete_ppp_secrets');

                // Live Active Connections
                Route::get('active', [PppActiveController::class, 'index']);
                Route::post('active/{sessionId}/kick', [PppActiveController::class, 'kick']);
            });
        });
    });
});
