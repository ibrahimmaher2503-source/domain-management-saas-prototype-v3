<?php

use App\Http\Controllers\DomainCheckoutController;
use App\Http\Controllers\DomainController;
use App\Http\Controllers\DomainDnsController;
use App\Http\Controllers\DomainRenewalController;
use App\Http\Controllers\DomainSearchController;
use App\Http\Controllers\DomainSecurityController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SslController;
use App\Http\Controllers\TransferController;
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
    Route::post('/domains/{domain}/renewal', [DomainRenewalController::class, 'store'])->name('domains.renewal.store');
    Route::put('/domains/{domain}/nameservers', [DomainController::class, 'updateNameservers'])->name('domains.nameservers.update');
    Route::put('/domains/{domain}/transfer-lock', [DomainSecurityController::class, 'updateTransferLock'])->name('domains.security.transfer-lock');
    Route::post('/domains/{domain}/auth-code', [DomainSecurityController::class, 'authCode'])->middleware('throttle:5,1')->name('domains.security.auth-code');
    Route::post('/domains/{domain}/dns/connect', [DomainDnsController::class, 'connect'])->name('domains.dns.connect');
    Route::post('/domains/{domain}/dns/sync', [DomainDnsController::class, 'sync'])->name('domains.dns.sync');
    Route::post('/domains/{domain}/dns/records', [DomainDnsController::class, 'store'])->name('domains.dns.records.store');
    Route::patch('/domains/{domain}/dns/records/{record}', [DomainDnsController::class, 'update'])->name('domains.dns.records.update');
    Route::delete('/domains/{domain}/dns/records/{record}', [DomainDnsController::class, 'destroy'])->name('domains.dns.records.destroy');
    Route::get('/transfers', [TransferController::class, 'index'])->name('transfers');
    Route::get('/transfers/new', [TransferController::class, 'create'])->name('transfers.create');
    Route::post('/transfers', [TransferController::class, 'store'])->name('transfers.store');
    Route::get('/transfers/{transfer}', [TransferController::class, 'show'])->name('transfers.show');
    Route::post('/transfers/{transfer}/refresh', [TransferController::class, 'refresh'])->name('transfers.refresh');
    Route::post('/transfers/{transfer}/cancel', [TransferController::class, 'cancel'])->name('transfers.cancel');
    Route::get('/ssl', [SslController::class, 'index'])->name('ssl');
    Route::get('/ssl/{certificate}', [SslController::class, 'show'])->name('ssl.show');
    Route::get('/domains/{domain}/ssl', [SslController::class, 'quote'])->name('ssl.create');
    Route::post('/domains/{domain}/ssl/parse', [SslController::class, 'parse'])->name('ssl.parse');
    Route::post('/domains/{domain}/ssl', [SslController::class, 'store'])->name('ssl.store');
    Route::post('/ssl/{certificate}/refresh', [SslController::class, 'refresh'])->name('ssl.refresh');
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
