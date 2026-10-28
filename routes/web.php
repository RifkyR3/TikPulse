<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Auth\EmailVerificationController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Sanctum\PersonalAccessToken;

/*
|--------------------------------------------------------------------------
| Web Routes - Inertia.js Svelte 5 Application
|--------------------------------------------------------------------------
*/

Route::get('/login', function () {
    return Inertia::render('auth/login');
})->name('login');

Route::match(['get', 'post'], '/logout', function (Request $request) {
    /** @var User|null $user */
    $user = $request->user();

    $tokenString = $request->cookie('tikpulse_token');
    if (is_string($tokenString) && $tokenString !== '') {
        PersonalAccessToken::findToken($tokenString)?->delete();
    }

    if ($user && $user->role === 'customer') {
        $user->tokens()->where('name', 'tikpulse_auth_token')->delete();
    }

    Auth::guard('web')->logout();

    if ($request->hasSession()) {
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }

    return redirect('/login')
        ->withCookie(cookie()->forget('tikpulse_token', '/'))
        ->withCookie(cookie()->forget('tikpulse_session', '/'));
})->name('web.logout');

Route::get('/', function (Request $request) {
    if ($request->user()?->role === 'customer') {
        return redirect('/status');
    }

    return redirect('/dashboard');
});

Route::get('/dashboard', function (Request $request) {
    if ($request->user()?->role === 'customer') {
        return redirect('/status');
    }

    return Inertia::render('dashboard/Dashboard');
})->name('dashboard');

Route::get('/routers', function () {
    return Inertia::render('routers/Index');
})->name('routers');

Route::get('/vouchers', function () {
    return Inertia::render('hotspot/VouchersIndex');
})->name('vouchers');

Route::get('/dedicated-users', function () {
    return Inertia::render('ppp/DedicatedUsers');
})->name('dedicated-users');

Route::get('/audit-logs', function () {
    return Inertia::render('safe-mode/AuditLogs');
})->name('audit-logs');

Route::get('/users', function () {
    return Inertia::render('user/UsersIndex');
})->name('users');

Route::get('/status', function () {
    return Inertia::render('customer/Status');
})->name('customer.status');

Route::middleware(['auth'])->group(function (): void {
    Route::post('/email/verify-code', [EmailVerificationController::class, 'verifyCode'])
        ->middleware('throttle:10,1')
        ->name('verification.code');
});
