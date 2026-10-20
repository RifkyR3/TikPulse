<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Web Routes - Inertia.js Svelte 5 Application
|--------------------------------------------------------------------------
*/

Route::get('/login', function () {
    return Inertia::render('auth/login');
})->name('login');

Route::get('/', function () {
    return redirect('/dashboard');
});

Route::get('/dashboard', function () {
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
