<?php

namespace App\Http\Controllers;

use App\Domain\Billing\DTOs\PaymentBillingData;
use App\Domain\Domains\Exceptions\CheckoutUnavailable;
use App\Domain\Ssl\Services\SslQuoteService;
use App\Integrations\OnlineNic\Exceptions\OnlineNicException;
use App\Jobs\ReconcileSslCertificate;
use App\Models\Domain;
use App\Models\SslCertificate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class SslController extends Controller
{
    public function index(Request $r): Response
    {
        return Inertia::render('SSL/Index', ['certificates' => $r->user()->sslCertificates()->with('domain')->latest()->get()->map(fn ($c) => $this->data($c)), 'domains' => $r->user()->domains()->where('status', 'active')->get(['id', 'name'])]);
    }

    public function show(Request $r, SslCertificate $certificate): Response
    {
        abort_unless($certificate->user_id === $r->user()->id, 404);

        return Inertia::render('SSL/Show', ['certificate' => $this->data($certificate), 'notice' => session('ssl_notice')]);
    }

    public function quote(Request $r, Domain $domain, SslQuoteService $s): Response
    {
        abort_unless($domain->user_id === $r->user()->id, 404);
        try {
            $q = $s->quote($r->user(), $domain, (string) $r->query('product', 'dv'), (int) $r->query('validity', 12));
            $emails = $s->approvers($r->user(), $domain);
        } catch (CheckoutUnavailable|OnlineNicException $e) {
            $q = null;
            $emails = [];
            $error = $e->getMessage();
        }

        return Inertia::render('SSL/Create', ['domain' => $domain->only('id', 'name'), 'products' => config('ssl.products'), 'quote' => $q, 'approver_emails' => $emails, 'error' => $error ?? null]);
    }

    public function parse(Request $r, Domain $domain, SslQuoteService $s): RedirectResponse
    {
        $data = $r->validate(['product' => 'required|string', 'csr' => 'required|string|max:12000']);
        try {
            $parsed = $s->parse($r->user(), $domain, $data['product'], $data['csr']);
        } catch (\Throwable $e) {
            return back()->withErrors(['csr' => $e instanceof CheckoutUnavailable ? $e->getMessage() : 'CSR could not be validated.']);
        }

        return back()->with('ssl_csr', $parsed);
    }

    public function store(Request $r, Domain $domain, SslQuoteService $s): RedirectResponse
    {
        abort_unless($domain->user_id === $r->user()->id, 404);
        $d = $r->validate(['product' => 'required|string', 'validity' => 'required|integer', 'csr' => 'required|string|max:12000', 'approver_email' => 'required|email', 'payment_billing' => 'required|array', 'payment_billing.first_name' => 'required|string', 'payment_billing.last_name' => 'required|string', 'payment_billing.email' => 'required|email', 'payment_billing.phone_number' => 'required|string', 'payment_billing.country' => 'required|string|size:2', 'payment_billing.city' => 'required|string', 'payment_billing.street' => 'required|string', 'payment_billing.state' => 'required|string', 'payment_billing.postal_code' => 'required|string']);
        try {
            $q = $s->quote($r->user(), $domain, $d['product'], (int) $d['validity']);
            $emails = $s->approvers($r->user(), $domain);
            if (! in_array($d['approver_email'], $emails, true)) {
                throw new CheckoutUnavailable('Choose an approver email returned by the provider.');
            } $p = config("ssl.products.{$d['product']}");
            $c = $r->user()->sslCertificates()->create(['domain_id' => $domain->id, 'provider' => 'onlinenic', 'product_key' => $d['product'], 'provider_product' => $p['provider_product'], 'status' => 'awaiting_payment', 'validation_type' => $p['validation_type'], 'approver_email' => $d['approver_email']]);
            $o = $r->user()->orders()->create(['type' => 'ssl_certificate', 'status' => 'awaiting_payment', 'domain' => $domain->name, 'tld' => $domain->tld, 'registration_period' => (int) $d['validity'] / 12, 'provider' => 'onlinenic', 'provider_cost' => null, 'customer_price' => $q['customer_price'], 'currency' => $q['currency'], 'premium' => false, 'registration_data' => null, 'ssl_data' => ['product' => $d['product'], 'validity' => (int) $d['validity'], 'csr' => $d['csr'], 'approver_email' => $d['approver_email'], 'first_name' => $d['payment_billing']['first_name'], 'last_name' => $d['payment_billing']['last_name'], 'email' => $d['payment_billing']['email'], 'phone' => $d['payment_billing']['phone_number']], 'billing_data' => (new PaymentBillingData($d['payment_billing']['first_name'], $d['payment_billing']['last_name'], $d['payment_billing']['email'], $d['payment_billing']['phone_number'], strtoupper($d['payment_billing']['country']), $d['payment_billing']['city'], $d['payment_billing']['street'], $d['payment_billing']['state'], $d['payment_billing']['postal_code']))->toArray(), 'nameservers' => [], 'ssl_certificate_id' => $c->id]);
            $c->update(['order_id' => $o->id]);

            return redirect()->route('orders.show', $o);
        } catch (CheckoutUnavailable|OnlineNicException $e) {
            return back()->withErrors(['ssl' => $e->getMessage()]);
        }
    }

    public function refresh(Request $r, SslCertificate $certificate): RedirectResponse
    {
        abort_unless($certificate->user_id === $r->user()->id, 404);
        if ($certificate->provider_order_id) {
            ReconcileSslCertificate::dispatch($certificate->id)->onConnection('database');
        }

        return back()->with('ssl_notice', 'Certificate status refresh queued.');
    }

    private function data(SslCertificate $c): array
    {
        return ['id' => $c->id, 'created_at' => $c->created_at?->toIso8601String(), 'domain' => $c->domain?->name, 'product' => $c->product_key, 'status' => $c->status, 'validation_type' => $c->validation_type, 'approver_email' => $c->approver_email, 'issued_at' => $c->issued_at?->toIso8601String(), 'expires_at' => $c->expires_at?->toDateString(), 'provider_synced_at' => $c->provider_synced_at?->toIso8601String()];
    }
}
