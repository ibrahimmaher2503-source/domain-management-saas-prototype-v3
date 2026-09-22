<?php

use App\Http\Controllers\Admin\AdminActionController;
use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\DomainCheckoutController;
use App\Http\Controllers\DomainController;
use App\Http\Controllers\DomainDnsController;
use App\Http\Controllers\DomainRenewalController;
use App\Http\Controllers\DomainSearchController;
use App\Http\Controllers\DomainSecurityController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReadinessController;
use App\Http\Controllers\SslController;
use App\Http\Controllers\TransferController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn (Request $request) => redirect()->route($request->user() ? 'overview' : 'login'));

Route::get('/health/ready', ReadinessController::class)->name('health.ready');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/overview', function (Request $request) {
        $domains = $request->user()->domains()->latest()->get();

        return Inertia::render('Overview/Index', ['metrics' => [
            'total' => $domains->count(),
            'active' => $domains->where('status', 'active')->count(),
            'expiring' => $domains->filter(fn ($domain) => $domain->expires_at?->between(today(), today()->addDays(30)))->count(),
            'transfers' => $request->user()->transfers()->whereIn('status', ['pending', 'processing', 'action_required'])->count(),
        ], 'domains' => $domains->take(5)->map(fn ($domain) => ['id' => $domain->id, 'name' => $domain->name, 'status' => $domain->status, 'expires_at' => $domain->expires_at?->toDateString()])->values()]);
    })->name('overview');
    Route::redirect('/dashboard', '/overview')->name('dashboard');
    Route::get('/domains', [DomainController::class, 'index'])->name('domains');
    Route::get('/domains/search', [DomainSearchController::class, 'show'])->middleware('throttle:30,1')->name('domains.search');
    Route::get('/checkout/domain', [DomainCheckoutController::class, 'create'])->name('checkout.domain');
    Route::post('/checkout/domain', [DomainCheckoutController::class, 'store'])->middleware('throttle:10,1')->name('checkout.domain.store');
    Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
    Route::post('/orders/{order}/pay', [PaymentController::class, 'pay'])->middleware('throttle:5,1')->name('orders.pay');
    Route::get('/domains/{domain}', [DomainController::class, 'show'])->name('domains.show');
    Route::post('/domains/{domain}/sync', [DomainController::class, 'sync'])->middleware('throttle:10,1')->name('domains.sync');
    Route::post('/domains/{domain}/renewal', [DomainRenewalController::class, 'store'])->name('domains.renewal.store');
    Route::put('/domains/{domain}/nameservers', [DomainController::class, 'updateNameservers'])->middleware('throttle:10,1')->name('domains.nameservers.update');
    Route::put('/domains/{domain}/transfer-lock', [DomainSecurityController::class, 'updateTransferLock'])->middleware('throttle:10,1')->name('domains.security.transfer-lock');
    Route::post('/domains/{domain}/auth-code', [DomainSecurityController::class, 'authCode'])->middleware('throttle:5,1')->name('domains.security.auth-code');
    Route::post('/domains/{domain}/dns/connect', [DomainDnsController::class, 'connect'])->middleware('throttle:10,1')->name('domains.dns.connect');
    Route::post('/domains/{domain}/dns/sync', [DomainDnsController::class, 'sync'])->middleware('throttle:20,1')->name('domains.dns.sync');
    Route::post('/domains/{domain}/dns/records', [DomainDnsController::class, 'store'])->middleware('throttle:30,1')->name('domains.dns.records.store');
    Route::patch('/domains/{domain}/dns/records/{record}', [DomainDnsController::class, 'update'])->middleware('throttle:30,1')->name('domains.dns.records.update');
    Route::delete('/domains/{domain}/dns/records/{record}', [DomainDnsController::class, 'destroy'])->middleware('throttle:30,1')->name('domains.dns.records.destroy');
    Route::get('/transfers', [TransferController::class, 'index'])->name('transfers');
    Route::get('/transfers/new', [TransferController::class, 'create'])->middleware('throttle:20,1')->name('transfers.create');
    Route::post('/transfers', [TransferController::class, 'store'])->middleware('throttle:5,1')->name('transfers.store');
    Route::get('/transfers/{transfer}', [TransferController::class, 'show'])->name('transfers.show');
    Route::post('/transfers/{transfer}/refresh', [TransferController::class, 'refresh'])->middleware('throttle:10,1')->name('transfers.refresh');
    Route::post('/transfers/{transfer}/cancel', [TransferController::class, 'cancel'])->middleware('throttle:5,1')->name('transfers.cancel');
    Route::get('/ssl', [SslController::class, 'index'])->name('ssl');
    Route::get('/ssl/{certificate}', [SslController::class, 'show'])->name('ssl.show');
    Route::get('/domains/{domain}/ssl', [SslController::class, 'quote'])->middleware('throttle:20,1')->name('ssl.create');
    Route::post('/domains/{domain}/ssl/parse', [SslController::class, 'parse'])->middleware('throttle:10,1')->name('ssl.parse');
    Route::post('/domains/{domain}/ssl', [SslController::class, 'store'])->middleware('throttle:5,1')->name('ssl.store');
    Route::post('/ssl/{certificate}/refresh', [SslController::class, 'refresh'])->middleware('throttle:10,1')->name('ssl.refresh');
    Route::post('/ssl/{certificate}/cancel', [SslController::class, 'cancel'])->middleware('throttle:5,1')->name('ssl.cancel');
    Route::post('/ssl/{certificate}/approver-email', [SslController::class, 'changeApproverEmail'])->middleware('throttle:5,1')->name('ssl.approver-email');
    Route::post('/ssl/{certificate}/resend-approver-email', [SslController::class, 'resendApproverEmail'])->middleware('throttle:5,1')->name('ssl.resend-approver-email');
    Route::post('/ssl/{certificate}/reissue', [SslController::class, 'reissue'])->middleware('throttle:5,1')->name('ssl.reissue');
    Route::post('/ssl/{certificate}/resend-fulfillment-email', [SslController::class, 'resendFulfillmentEmail'])->middleware('throttle:5,1')->name('ssl.resend-fulfillment-email');
    Route::get('/billing', fn (Request $request) => Inertia::render('Billing/Index', ['payments' => $request->user()->payments()->with('order:id,type,domain')->latest()->get(['id', 'order_id', 'status', 'amount', 'currency', 'paid_at', 'created_at'])]))->name('billing');
    Route::get('/settings', fn () => Inertia::render('Settings/Index'))->name('settings');
});

Route::prefix('admin')->name('admin.')->middleware(['auth', 'verified', 'admin'])->group(function () {
    Route::get('/', [AdminController::class, 'overview'])->name('overview');
    Route::get('/customers', [AdminController::class, 'customers'])->name('customers');
    Route::get('/customers/create', [AdminController::class, 'createCustomer'])->name('customers.create');
    Route::post('/customers', [AdminController::class, 'storeCustomer'])->name('customers.store');
    Route::get('/customers/{customer}', [AdminController::class, 'customer'])->name('customers.show');
    Route::post('/customers/{customer}/activation', [AdminController::class, 'regenerateActivation'])->name('customers.activation');
    Route::get('/domains', [AdminController::class, 'domains'])->name('domains');
    Route::get('/domains/import', [AdminController::class, 'importDomain'])->name('domains.import');
    Route::post('/domains/import', [AdminController::class, 'storeImportedDomain'])->name('domains.import.store');
    Route::get('/domains/{domain}', [AdminController::class, 'domain'])->name('domains.show');
    Route::get('/orders', [AdminController::class, 'orders'])->name('orders');
    Route::get('/orders/{order}', [AdminController::class, 'order'])->name('orders.show');
    Route::get('/payments', [AdminController::class, 'payments'])->name('payments');
    Route::get('/transfers', [AdminController::class, 'transfers'])->name('transfers');
    Route::get('/transfers/{transfer}', [AdminController::class, 'transfer'])->name('transfers.show');
    Route::get('/ssl', [AdminController::class, 'ssl'])->name('ssl');
    Route::get('/ssl/{certificate}', [AdminController::class, 'certificate'])->name('ssl.show');
    Route::get('/operations', [AdminController::class, 'operations'])->name('operations');
    Route::post('/reconcile/{type}/{id}', [AdminActionController::class, 'reconcile'])->middleware('throttle:10,1')->name('reconcile');
    Route::get('/providers', [AdminController::class, 'providers'])->name('providers');
    Route::post('/providers/onlinenic/balance', [AdminActionController::class, 'refreshBalance'])->middleware('throttle:5,1')->name('providers.balance');
    Route::get('/pricing', [AdminController::class, 'pricing'])->name('pricing');
});

Route::post('/payments/paymob/callback', [PaymentController::class, 'callback'])->middleware('paymob.size')->name('payments.paymob.callback');
Route::get('/payments/paymob/return', [PaymentController::class, 'return'])->name('payments.paymob.return');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
