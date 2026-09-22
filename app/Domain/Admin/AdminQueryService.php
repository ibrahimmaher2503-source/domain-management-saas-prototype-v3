<?php

namespace App\Domain\Admin;

use App\Domain\Registrar\Contracts\RegistrarGateway;
use App\Domain\Registrar\DTOs\DomainPriceQuery;
use App\Models\DnsOperation;
use App\Models\Domain;
use App\Models\Order;
use App\Models\Payment;
use App\Models\RegistrarOperation;
use App\Models\SslCertificate;
use App\Models\Transfer;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

final class AdminQueryService
{
    private const PAGE_SIZE = 25;

    public function overview(): array
    {
        $today = today();

        return [
            'metrics' => [
                'customers' => User::where('is_admin', false)->count(),
                'domains' => Domain::count(),
                'active_domains' => Domain::where('status', 'active')->count(),
                'expiring_7_days' => Domain::whereDate('expires_at', '>=', $today)->whereDate('expires_at', '<=', $today->copy()->addDays(7))->count(),
                'expiring_30_days' => Domain::whereDate('expires_at', '>=', $today)->whereDate('expires_at', '<=', $today->copy()->addDays(30))->count(),
                'pending_transfers' => Transfer::whereIn('status', ['pending', 'processing', 'ambiguous', 'action_required'])->count(),
                'pending_ssl' => SslCertificate::whereIn('status', ['pending_validation', 'processing', 'ambiguous', 'action_required'])->count(),
                'awaiting_payment_orders' => Order::where('status', 'awaiting_payment')->count(),
                'provisioning_orders' => Order::where('status', 'provisioning')->count(),
                'failed_orders' => Order::where('status', 'failed')->count(),
                'external_attention' => RegistrarOperation::whereIn('status', ['ambiguous', 'action_required'])->count()
                    + DnsOperation::whereIn('status', ['ambiguous', 'action_required'])->count(),
            ],
            'recent' => [
                'payments' => Payment::with('user:id,name,email')->where('status', 'paid')->latest('paid_at')->limit(5)->get(['id', 'user_id', 'order_id', 'amount', 'currency', 'paid_at']),
                'registrations' => $this->recentOrders('domain_registration'),
                'renewals' => $this->recentOrders('domain_renewal'),
                'transfers' => Transfer::with('user:id,name,email')->latest()->limit(5)->get(['id', 'user_id', 'domain', 'status', 'updated_at']),
                'ssl' => SslCertificate::with(['user:id,name,email', 'domain:id,name'])->latest()->limit(5)->get(['id', 'user_id', 'domain_id', 'status', 'updated_at']),
            ],
        ];
    }

    public function customers(Request $request): array
    {
        $search = trim((string) $request->query('search'));
        $query = User::query()->where('is_admin', false)
            ->withCount(['domains', 'orders', 'payments as paid_payments_count' => fn (Builder $q) => $q->where('status', 'paid')]);
        if ($search !== '') {
            $query->where(fn (Builder $q) => $q->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
        }

        return ['customers' => $query->latest()->paginate(self::PAGE_SIZE)->withQueryString(), 'filters' => ['search' => $search]];
    }

    public function customer(User $customer): array
    {
        return ['customer' => $customer->only(['id', 'name', 'email', 'created_at']), 'related' => [
            'domains' => $customer->domains()->latest()->limit(25)->get(['id', 'name', 'status', 'expires_at', 'provider']),
            'orders' => $customer->orders()->latest()->limit(25)->get(['id', 'type', 'domain', 'status', 'customer_price', 'currency', 'created_at']),
            'payments' => $customer->payments()->latest()->limit(25)->get(['id', 'order_id', 'provider', 'status', 'amount', 'currency', 'paid_at', 'failed_at']),
            'transfers' => $customer->transfers()->latest()->limit(25)->get(['id', 'domain', 'direction', 'status', 'provider_status', 'updated_at']),
            'ssl' => $customer->sslCertificates()->with('domain:id,name')->latest()->limit(25)->get(['id', 'domain_id', 'product_key', 'status', 'provider_status', 'expires_at']),
        ]];
    }

    public function domains(Request $request): array
    {
        $query = Domain::query()->with(['user:id,name,email', 'dnsZone:id,domain_id,status']);
        $search = trim((string) $request->query('search'));
        if ($search !== '') {
            $query->where(fn (Builder $q) => $q->where('name', 'like', "%{$search}%")->orWhereHas('user', fn (Builder $u) => $u->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")));
        }
        $this->exact($query, $request, ['status', 'provider']);
        $expiration = (string) $request->query('expiration');
        $today = today();
        match ($expiration) {
            'expired' => $query->whereDate('expires_at', '<', $today),
            '7', '30', '60' => $query->whereDate('expires_at', '>=', $today)->whereDate('expires_at', '<=', $today->copy()->addDays((int) $expiration)),
            'unknown' => $query->whereNull('expires_at'),
            default => null,
        };

        return ['domains' => $query->latest()->paginate(self::PAGE_SIZE)->withQueryString(), 'filters' => $request->only(['search', 'status', 'provider', 'expiration'])];
    }

    public function domain(Domain $domain): array
    {
        $domain->load(['user:id,name,email', 'order:id,type,status,customer_price,currency', 'dnsZone:id,domain_id,provider,status,provider_status,provider_synced_at']);

        return ['domain' => $domain->only(['id', 'name', 'status', 'provider', 'provider_status', 'registered_at', 'expires_at', 'nameservers', 'transfer_locked', 'provider_synced_at']) + [
            'customer' => $domain->user, 'registration_order' => $domain->order, 'dns' => $domain->dnsZone,
        ], 'related' => [
            'renewals' => $domain->renewalOrders()->where('type', 'domain_renewal')->latest()->limit(25)->get(['id', 'status', 'customer_price', 'currency', 'created_at']),
            'transfers' => $domain->transfers()->latest()->limit(25)->get(['id', 'direction', 'status', 'provider_status', 'updated_at']),
            'ssl' => $domain->sslCertificates()->latest()->limit(25)->get(['id', 'product_key', 'status', 'provider_status', 'expires_at']),
            'activity' => $domain->registrarOperations()->latest()->limit(25)->get(['id', 'operation', 'provider', 'status', 'provider_code', 'started_at', 'completed_at']),
        ]];
    }

    public function orders(Request $request): array
    {
        $query = Order::query()->with('user:id,name,email');
        $this->exact($query, $request, ['type', 'status']);
        $this->customerFilter($query, $request);
        $this->dateRange($query, $request);

        return ['orders' => $query->latest()->paginate(self::PAGE_SIZE)->withQueryString(), 'filters' => $request->only(['type', 'status', 'customer', 'from', 'to'])];
    }

    public function order(Order $order): array
    {
        $order->load(['user:id,name,email', 'payments' => fn ($q) => $q->select(['id', 'order_id', 'provider', 'status', 'amount', 'currency', 'paid_at', 'failed_at']), 'registeredDomain:id,order_id,name,status', 'renewalDomain:id,name,status,expires_at', 'sslCertificate:id,domain_id,status,provider_status', 'registrarOperations' => fn ($q) => $q->select(['id', 'order_id', 'operation', 'provider', 'status', 'provider_code', 'started_at', 'completed_at'])->latest()]);

        return ['order' => $order->only(['id', 'type', 'status', 'domain', 'tld', 'registration_period', 'provider', 'provider_cost', 'customer_price', 'currency', 'provisioning_failure_reason', 'created_at', 'updated_at']) + ['customer' => $order->user, 'payments' => $order->payments, 'domain_record' => $order->registeredDomain ?? $order->renewalDomain, 'ssl' => $order->sslCertificate, 'activity' => $order->registrarOperations]];
    }

    public function payments(Request $request): array
    {
        $query = Payment::query()->select(['id', 'user_id', 'order_id', 'provider', 'status', 'amount', 'currency', 'paid_at', 'failed_at', 'created_at', 'updated_at'])->with(['user:id,name,email', 'order:id,type,domain']);
        $this->exact($query, $request, ['status', 'provider']);
        $this->customerFilter($query, $request);

        return ['payments' => $query->latest()->paginate(self::PAGE_SIZE)->withQueryString(), 'filters' => $request->only(['status', 'provider', 'customer'])];
    }

    public function transfers(Request $request): array
    {
        $query = Transfer::query()->with('user:id,name,email');
        $this->exact($query, $request, ['status', 'direction']);
        $this->customerFilter($query, $request);

        return ['transfers' => $query->latest()->paginate(self::PAGE_SIZE)->withQueryString(), 'filters' => $request->only(['status', 'direction', 'customer'])];
    }

    public function transfer(Transfer $transfer): array
    {
        $transfer->load(['user:id,name,email', 'order' => fn ($q) => $q->select(['id', 'status', 'customer_price', 'currency'])->with('payments:id,order_id,provider,status,amount,currency,paid_at'), 'domainRecord:id,name,status,expires_at']);
        $operations = RegistrarOperation::where('order_id', $transfer->order_id)->latest()->get(['id', 'operation', 'provider', 'status', 'provider_code', 'started_at', 'completed_at']);

        return ['transfer' => $transfer->only(['id', 'domain', 'direction', 'status', 'provider', 'provider_status', 'requested_at', 'provider_synced_at', 'completed_at', 'cancelled_at']) + ['customer' => $transfer->user, 'order' => $transfer->order, 'domain_record' => $transfer->domainRecord, 'activity' => $operations]];
    }

    public function ssl(Request $request): array
    {
        $query = SslCertificate::query()->with(['user:id,name,email', 'domain:id,name']);
        $this->exact($query, $request, ['status', 'provider']);
        $this->customerFilter($query, $request);

        return ['certificates' => $query->latest()->paginate(self::PAGE_SIZE)->withQueryString(), 'filters' => $request->only(['status', 'provider', 'customer'])];
    }

    public function certificate(SslCertificate $certificate): array
    {
        $certificate->load(['user:id,name,email', 'domain:id,name,status,expires_at', 'order' => fn ($q) => $q->select(['id', 'status', 'customer_price', 'currency'])->with('payments:id,order_id,provider,status,amount,currency,paid_at')]);
        $operations = RegistrarOperation::where('order_id', $certificate->order_id)->latest()->get(['id', 'operation', 'provider', 'status', 'provider_code', 'started_at', 'completed_at']);

        return ['certificate' => $certificate->only(['id', 'provider', 'provider_order_id', 'product_key', 'provider_product', 'status', 'provider_status', 'validation_type', 'approver_email', 'issued_at', 'expires_at', 'provider_synced_at']) + ['customer' => $certificate->user, 'domain' => $certificate->domain, 'order' => $certificate->order, 'activity' => $operations]];
    }

    public function operations(Request $request): array
    {
        $category = "CASE WHEN o.operation = 'domain_registration' THEN 'registration' WHEN o.operation = 'domain_renewal' THEN 'renewal' WHEN o.operation LIKE '%nameserver%' THEN 'nameservers' WHEN o.operation IN ('set_transfer_lock', 'get_auth_code') THEN 'security' WHEN o.operation LIKE '%transfer%' THEN 'transfer' WHEN o.operation LIKE '%certificate%' OR o.operation LIKE '%approver%' OR o.operation LIKE 'ssl_%' THEN 'ssl' ELSE 'registration' END";
        $registrar = DB::table('registrar_operations as o')->leftJoin('users as u', 'u.id', '=', 'o.user_id')->leftJoin('domains as d', 'd.id', '=', 'o.domain_id')->leftJoin('orders as ord', 'ord.id', '=', 'o.order_id')->selectRaw("'registrar' as source, {$category} as category, o.id, o.id as operation_id, o.operation, COALESCE(d.name, ord.domain, '') as resource, u.name as customer, o.provider, o.status, o.started_at, o.completed_at");
        $dns = DB::table('dns_operations as o')->join('dns_zones as z', 'z.id', '=', 'o.dns_zone_id')->join('domains as d', 'd.id', '=', 'z.domain_id')->join('users as u', 'u.id', '=', 'd.user_id')->selectRaw("'dns' as source, 'dns' as category, z.id, o.id as operation_id, o.operation, d.name as resource, u.name as customer, z.provider, o.status, o.created_at as started_at, o.updated_at as completed_at");
        $query = DB::query()->fromSub($registrar->unionAll($dns), 'operations');
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($source = $request->query('source')) {
            $query->where('source', $source);
        }
        if ($categoryFilter = $request->query('category')) {
            $query->where('category', $categoryFilter);
        }

        return ['operations' => $query->orderByDesc('started_at')->paginate(self::PAGE_SIZE)->withQueryString(), 'filters' => $request->only(['status', 'source', 'category']), 'alerts' => [
            'paid_failed_fulfillment' => Order::where('status', 'failed')->whereHas('payments', fn (Builder $q) => $q->where('status', 'paid'))->count(),
            'stale_pending' => RegistrarOperation::where('status', 'pending')->where('started_at', '<', now()->subMinutes(15))->count() + DnsOperation::where('status', 'pending')->where('created_at', '<', now()->subMinutes(15))->count(),
            'ambiguous' => RegistrarOperation::where('status', 'ambiguous')->count() + DnsOperation::where('status', 'ambiguous')->count(),
            'action_required' => Transfer::where('status', 'action_required')->count() + SslCertificate::where('status', 'action_required')->count(),
        ]];
    }

    public function providers(): array
    {
        return ['providers' => [
            'onlinenic' => ['configured' => $this->configured(['onlinenic.client_id', 'onlinenic.password']), 'endpoint' => config('onlinenic.host').':'.config('onlinenic.port'), 'currency' => config('onlinenic.account_currency'), 'contacts_configured' => $this->configured(['onlinenic.registrant_contact_id', 'onlinenic.admin_contact_id', 'onlinenic.tech_contact_id', 'onlinenic.billing_contact_id']), 'last_success' => RegistrarOperation::where('provider', 'onlinenic')->where('status', 'completed')->max('completed_at')],
            'paymob' => ['configured' => $this->configured(['paymob.secret_key', 'paymob.public_key', 'paymob.hmac_secret']), 'mode' => config('paymob.mode'), 'integration_configured' => filled(config('paymob.card_integration_id')), 'last_success' => Payment::where('provider', 'paymob')->where('status', 'paid')->max('paid_at')],
            'cloudflare' => ['configured' => $this->configured(['cloudflare.api_token']), 'account_configured' => filled(config('cloudflare.account_id')), 'token_configured' => filled(config('cloudflare.api_token')), 'last_success' => DB::table('dns_zones')->where('status', 'active')->max('provider_synced_at')],
        ]];
    }

    public function pricing(Request $request, RegistrarGateway $registrar): array
    {
        $providerPrice = null;
        if ($request->boolean('refresh_price')) {
            try {
                $price = $registrar->getDomainPrice(new DomainPriceQuery('example.com', 'registration', 1));
                $providerPrice = ['amount' => $price->amount, 'currency' => $price->currency];
            } catch (Throwable) {
                $providerPrice = ['unavailable' => true];
            }
        }

        return ['pricing' => [
            ['name' => 'Domain registration markup', 'source' => 'DOMAIN_REGISTRATION_MARKUP_PERCENT', 'value' => config('domain.registration_markup_percent').'%', 'enabled' => true],
            ['name' => 'Domain renewal markup', 'source' => 'Shared domain markup', 'value' => config('domain.registration_markup_percent').'%', 'enabled' => true],
            ['name' => 'Domain transfer markup', 'source' => 'Shared domain markup', 'value' => config('domain.registration_markup_percent').'%', 'enabled' => true],
            ...collect(config('ssl.products', []))->map(fn (array $product, string $key) => ['name' => 'SSL '.$product['name'], 'source' => 'ssl.products.'.$key, 'value' => $product['customer_price'] ? $product['customer_price'].' '.$product['currency'] : 'Not configured', 'enabled' => (bool) $product['enabled']])->values()->all(),
        ], 'provider_price' => $providerPrice];
    }

    private function recentOrders(string $type)
    {
        return Order::with('user:id,name,email')->where('type', $type)->latest()->limit(5)->get(['id', 'user_id', 'domain', 'status', 'updated_at']);
    }

    private function exact(Builder $query, Request $request, array $fields): void
    {
        foreach ($fields as $field) {
            if ($value = $request->query($field)) {
                $query->where($field, $value);
            }
        }
    }

    private function customerFilter(Builder $query, Request $request): void
    {
        if ($customer = trim((string) $request->query('customer'))) {
            $query->whereHas('user', fn (Builder $q) => $q->where('name', 'like', "%{$customer}%")->orWhere('email', 'like', "%{$customer}%"));
        }
    }

    private function dateRange(Builder $query, Request $request): void
    {
        if ($from = $request->date('from')) {
            $query->whereDate('created_at', '>=', $from);
        }
        if ($to = $request->date('to')) {
            $query->whereDate('created_at', '<=', $to);
        }
    }

    private function configured(array $keys): bool
    {
        return collect($keys)->every(fn (string $key) => filled(config($key)));
    }
}
