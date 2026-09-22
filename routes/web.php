<?php

use App\Http\Controllers\DomainCheckoutController;
use App\Http\Controllers\DomainController;
use App\Http\Controllers\DomainSearchController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PaymentController;
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
    Route::get('/domains', [DomainController::class, 'index'])->name('domains');
    Route::get('/domains/search', [DomainSearchController::class, 'show'])->name('domains.search');
    Route::get('/checkout/domain', [DomainCheckoutController::class, 'create'])->name('checkout.domain');
    Route::post('/checkout/domain', [DomainCheckoutController::class, 'store'])->name('checkout.domain.store');
    Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
    Route::post('/orders/{order}/pay', [PaymentController::class, 'pay'])->name('orders.pay');
    Route::get('/domains/{domain}', [DomainController::class, 'show'])->name('domains.show');
    Route::post('/domains/{domain}/sync', [DomainController::class, 'sync'])->name('domains.sync');
    Route::put('/domains/{domain}/nameservers', [DomainController::class, 'updateNameservers'])->name('domains.nameservers.update');
    Route::get('/transfers', fn () => Inertia::render('Transfers/Index'))->name('transfers');
    Route::get('/ssl', fn () => Inertia::render('SSL/Index'))->name('ssl');
    Route::get('/billing', fn () => Inertia::render('Billing/Index'))->name('billing');
    Route::get('/settings', fn () => Inertia::render('Settings/Index'))->name('settings');
});

Route::post('/payments/paymob/callback', [PaymentController::class, 'callback'])->name('payments.paymob.callback');
Route::get('/payments/paymob/return', [PaymentController::class, 'return'])->name('payments.paymob.return');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
