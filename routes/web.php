<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/overview', fn () => Inertia::render('Overview/Index'))->name('overview');
    Route::get('/dashboard', fn () => Inertia::render('Overview/Index'))->name('dashboard');
    Route::get('/domains', fn () => Inertia::render('Domains/Index'))->name('domains');
    Route::get('/domains/{domain}', fn (int $domain) => Inertia::render('Domains/Show', ['domain' => $domain]))->name('domains.show');
    Route::get('/transfers', fn () => Inertia::render('Transfers/Index'))->name('transfers');
    Route::get('/ssl', fn () => Inertia::render('SSL/Index'))->name('ssl');
    Route::get('/billing', fn () => Inertia::render('Billing/Index'))->name('billing');
    Route::get('/settings', fn () => Inertia::render('Settings/Index'))->name('settings');
});


Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
