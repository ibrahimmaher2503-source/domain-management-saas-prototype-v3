<?php

namespace App\Http\Controllers;

use App\Domain\Billing\DTOs\PaymentBillingData;
use App\Domain\Domains\Exceptions\CheckoutUnavailable;
use App\Domain\Ssl\Services\SslMaintenanceService;
use App\Domain\Ssl\Services\SslQuoteService;
use App\Integrations\OnlineNic\Exceptions\OnlineNicException;
use App\Jobs\ReconcileSslCertificate;
use App\Models\Domain;
use App\Models\SslCertificate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        $activity = $certificate->order?->registrarOperations()->whereIn('operation', ['cancel_certificate', 'change_approver_email', 'resend_approver_email', 'reissue_certificate', 'resend_fulfillment_email'])->latest()->limit(20)->get()->map(fn ($op) => ['id' => $op->id, 'label' => match ($op->operation) {
            'cancel_certificate' => 'Certificate cancelled', 'change_approver_email' => 'Approver email changed', 'resend_approver_email' => 'Validation email resent', 'reissue_certificate' => 'Certificate reissue requested', 'resend_fulfillment_email' => 'Certificate fulfillment email resent',
        }, 'status' => $op->status, 'at' => $op->created_at?->toIso8601String()])->map(fn ($item) => in_array($item['status'], ['pending', 'ambiguous'], true) ? array_merge($item, ['label' => 'Certificate maintenance awaiting confirmation']) : ($item['status'] === 'failed' ? array_merge($item, ['label' => 'Certificate maintenance failed']) : $item)) ?? collect();

        return Inertia::render('SSL/Show', ['certificate' => $this->data($certificate) + ['actions' => SslMaintenanceService::actions($certificate)], 'activity' => $activity, 'notice' => session('ssl_notice'), 'error' => session('errors')?->first('ssl')]);
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
            }
            $p = config("ssl.products.{$d['product']}");
            $billing = (new PaymentBillingData($d['payment_billing']['first_name'], $d['payment_billing']['last_name'], $d['payment_billing']['email'], $d['payment_billing']['phone_number'], strtoupper($d['payment_billing']['country']), $d['payment_billing']['city'], $d['payment_billing']['street'], $d['payment_billing']['state'], $d['payment_billing']['postal_code']))->toArray();
            $o = DB::transaction(function () use ($r, $domain, $d, $p, $q, $billing) {
                $locked = Domain::query()->whereKey($domain->id)->lockForUpdate()->firstOrFail();
                abort_unless($locked->user_id === $r->user()->id, 404);
                if ($locked->sslCertificates()->where('product_key', $d['product'])->whereIn('status', ['awaiting_payment', 'ordering'])->exists()) {
                    throw new CheckoutUnavailable('An active SSL order already exists for this domain.');
                }
                $c = $r->user()->sslCertificates()->create(['domain_id' => $locked->id, 'provider' => 'onlinenic', 'product_key' => $d['product'], 'provider_product' => $p['provider_product'], 'status' => 'awaiting_payment', 'validation_type' => $p['validation_type'], 'approver_email' => $d['approver_email']]);
                $order = $r->user()->orders()->create(['type' => 'ssl_certificate', 'status' => 'awaiting_payment', 'domain' => $locked->name, 'tld' => $locked->tld, 'registration_period' => (int) $d['validity'] / 12, 'provider' => 'onlinenic', 'provider_cost' => null, 'customer_price' => $q['customer_price'], 'currency' => $q['currency'], 'premium' => false, 'registration_data' => null, 'ssl_data' => ['product' => $d['product'], 'validity' => (int) $d['validity'], 'csr' => $d['csr'], 'approver_email' => $d['approver_email'], 'first_name' => $d['payment_billing']['first_name'], 'last_name' => $d['payment_billing']['last_name'], 'email' => $d['payment_billing']['email'], 'phone' => $d['payment_billing']['phone_number']], 'billing_data' => $billing, 'nameservers' => [], 'ssl_certificate_id' => $c->id]);
                $c->update(['order_id' => $order->id]);

                return $order;
            });

            return redirect()->route('orders.show', $o);
        } catch (CheckoutUnavailable|OnlineNicException $e) {
            return back()->withErrors(['ssl' => $e->getMessage()]);
        }
    }

    public function refresh(Request $r, SslCertificate $certificate): RedirectResponse
    {
        abort_unless($certificate->user_id === $r->user()->id, 404);
        if ($certificate->provider_order_id) {
            ReconcileSslCertificate::dispatch($certificate->id);
        }

        return back()->with('ssl_notice', 'Certificate status refresh queued.');
    }

    public function cancel(Request $r, SslCertificate $certificate, SslMaintenanceService $service): RedirectResponse
    {
        $this->owned($r, $certificate);
        $r->validate(['confirm' => 'accepted']);

        return $this->maintenance(fn () => $service->cancel($certificate), 'Certificate cancelled.');
    }

    public function changeApproverEmail(Request $r, SslCertificate $certificate, SslMaintenanceService $service): RedirectResponse
    {
        $this->owned($r, $certificate);
        $data = $r->validate(['approver_email' => 'required|email', 'confirm_email' => 'required|same:approver_email']);

        return $this->maintenance(fn () => $service->changeApproverEmail($certificate->load('domain', 'order'), $data['approver_email']), 'Approver email changed.');
    }

    public function resendApproverEmail(Request $r, SslCertificate $certificate, SslMaintenanceService $service): RedirectResponse
    {
        $this->owned($r, $certificate);

        return $this->maintenance(fn () => $service->resendApproverEmail($certificate), 'Validation email resent.');
    }

    public function reissue(Request $r, SslCertificate $certificate, SslMaintenanceService $service): RedirectResponse
    {
        $this->owned($r, $certificate);
        $data = $r->validate(['csr' => 'required|string|max:12000', 'confirm' => 'accepted']);

        return $this->maintenance(fn () => $service->reissue($certificate->load('domain', 'order'), $data['csr']), 'Certificate reissue requested.');
    }

    public function resendFulfillmentEmail(Request $r, SslCertificate $certificate, SslMaintenanceService $service): RedirectResponse
    {
        $this->owned($r, $certificate);

        return $this->maintenance(fn () => $service->resendFulfillmentEmail($certificate), 'Certificate delivery email requested.');
    }

    private function owned(Request $request, SslCertificate $certificate): void
    {
        abort_unless($certificate->user_id === $request->user()->id, 404);
        $certificate->loadMissing('domain', 'order');
    }

    private function maintenance(callable $action, string $notice): RedirectResponse
    {
        try {
            $action();

            return back()->with('ssl_notice', $notice);
        } catch (CheckoutUnavailable|OnlineNicException $exception) {
            return back()->withErrors(['ssl' => $exception->getMessage()]);
        }
    }

    private function data(SslCertificate $c): array
    {
        return ['id' => $c->id, 'created_at' => $c->created_at?->toIso8601String(), 'domain' => $c->domain?->name, 'product' => $c->product_key, 'status' => $c->status, 'validation_type' => $c->validation_type, 'approver_email' => $c->approver_email, 'issued_at' => $c->issued_at?->toIso8601String(), 'expires_at' => $c->expires_at?->toDateString(), 'provider_synced_at' => $c->provider_synced_at?->toIso8601String()];
    }
}
