<?php

namespace Tests\Feature;

use App\Jobs\ReconcileDomainRegistration;
use App\Models\DnsZone;
use App\Models\Domain;
use App\Models\Order;
use App\Models\Payment;
use App\Models\RegistrarOperation;
use App\Models\SslCertificate;
use App\Models\Transfer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class AdminOperationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_access_is_forward_safe_and_customers_are_denied(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $customer = User::factory()->create();

        $this->actingAs($admin)->get(route('admin.overview'))->assertOk()->assertInertia(fn (Assert $page) => $page->component('Admin/Overview'));
        $this->actingAs($customer)->get(route('admin.overview'))->assertForbidden();
        $this->actingAs($customer)->get(route('admin.customers'))->assertForbidden();
    }

    public function test_overview_metrics_use_database_dates_and_operation_states(): void
    {
        [$admin, $customer] = $this->users();
        $order = $this->order($customer, 'domain_registration', 'awaiting_payment', 'seven.example.com');
        $this->domain($customer, $order, 'seven.example.com', now()->addDays(7));
        $this->domain($customer, $order, 'thirty.example.com', now()->addDays(30));
        $this->operation($customer, $order, 'domain_registration', 'ambiguous');
        Transfer::create(['user_id' => $customer->id, 'order_id' => $order->id, 'domain' => 'moving.example.com', 'tld' => 'com', 'provider' => 'onlinenic', 'direction' => 'in', 'status' => 'pending']);

        $this->actingAs($admin)->get(route('admin.overview'))->assertInertia(fn (Assert $page) => $page
            ->where('metrics.customers', 1)->where('metrics.domains', 2)->where('metrics.expiring_7_days', 1)
            ->where('metrics.expiring_30_days', 2)->where('metrics.pending_transfers', 1)
            ->where('metrics.awaiting_payment_orders', 1)->where('metrics.external_attention', 1));
    }

    public function test_customer_search_detail_and_sensitive_values_are_never_returned(): void
    {
        [$admin, $customer] = $this->users(['name' => 'Needle Customer', 'email' => 'needle@example.com']);
        $order = $this->order($customer, 'domain_registration', 'completed', 'safe.example.com', ['billing_data' => ['secret' => 'PRIVATE-BILLING'], 'domain_password' => 'EPP-SECRET']);
        Payment::create(['user_id' => $customer->id, 'order_id' => $order->id, 'provider' => 'paymob', 'status' => 'paid', 'amount' => '12.00', 'currency' => 'USD', 'provider_reference' => 'ref-safe', 'checkout_reference' => 'CHECKOUT-SECRET', 'provider_metadata' => ['token' => 'PAYLOAD-SECRET'], 'paid_at' => now()]);

        $this->actingAs($admin)->get(route('admin.customers', ['search' => 'needle']))->assertInertia(fn (Assert $page) => $page->has('customers.data', 1)->where('customers.data.0.email', 'needle@example.com'));
        $this->actingAs($admin)->get(route('admin.customers.show', $customer))->assertOk()->assertDontSee('PRIVATE-BILLING')->assertDontSee('EPP-SECRET')->assertDontSee('PAYLOAD-SECRET')->assertDontSee('CHECKOUT-SECRET');
    }

    public function test_domain_order_payment_transfer_and_ssl_filters_return_safe_fields(): void
    {
        [$admin, $customer] = $this->users();
        $order = $this->order($customer, 'ssl_certificate', 'completed', 'filtered.example.com', ['ssl_data' => ['csr' => 'CSR-SECRET']]);
        $domain = $this->domain($customer, $order, 'filtered.example.com', null);
        Payment::create(['user_id' => $customer->id, 'order_id' => $order->id, 'provider' => 'paymob', 'status' => 'failed', 'amount' => '10.00', 'currency' => 'USD', 'provider_reference' => 'ref-filter', 'provider_metadata' => ['raw' => 'RAW-PAYLOAD']]);
        Transfer::create(['user_id' => $customer->id, 'order_id' => $order->id, 'domain_id' => $domain->id, 'domain' => $domain->name, 'tld' => 'com', 'provider' => 'onlinenic', 'direction' => 'in', 'status' => 'action_required']);
        $certificate = SslCertificate::create(['user_id' => $customer->id, 'domain_id' => $domain->id, 'order_id' => $order->id, 'provider' => 'onlinenic', 'product_key' => 'dv', 'provider_product' => 'RapidSSL', 'status' => 'action_required']);
        $order->update(['ssl_certificate_id' => $certificate->id]);

        $this->actingAs($admin)->get(route('admin.domains', ['expiration' => 'unknown']))->assertInertia(fn (Assert $page) => $page->has('domains.data', 1));
        $this->actingAs($admin)->get(route('admin.orders', ['type' => 'ssl_certificate']))->assertInertia(fn (Assert $page) => $page->has('orders.data', 1));
        $this->actingAs($admin)->get(route('admin.payments', ['status' => 'failed']))->assertOk()->assertDontSee('RAW-PAYLOAD');
        $this->actingAs($admin)->get(route('admin.transfers', ['status' => 'action_required']))->assertInertia(fn (Assert $page) => $page->has('transfers.data', 1));
        $this->actingAs($admin)->get(route('admin.ssl.show', $certificate))->assertOk()->assertDontSee('CSR-SECRET');
    }

    public function test_operations_are_paginated_redacted_and_highlight_attention(): void
    {
        [$admin, $customer] = $this->users();
        $order = $this->order($customer, 'domain_registration', 'provisioning', 'ops.example.com');
        foreach (range(1, 26) as $index) {
            $this->operation($customer, $order, 'domain_registration', $index === 1 ? 'ambiguous' : 'completed', 'cltrid-secret-'.$index);
        }
        $domain = $this->domain($customer, $order, 'dns-ops.example.com', now()->addYear());
        $zone = DnsZone::create(['domain_id' => $domain->id, 'provider' => 'cloudflare', 'status' => 'pending']);
        $zone->operations()->create(['operation' => 'connect', 'status' => 'ambiguous']);

        $this->actingAs($admin)->get(route('admin.operations'))->assertOk()->assertDontSee('cltrid-secret')->assertDontSee('PROVIDER-CODE-SECRET')->assertInertia(fn (Assert $page) => $page
            ->has('operations.data', 25)->where('operations.per_page', 25)->where('alerts.ambiguous', 2));
        $this->actingAs($admin)->get(route('admin.operations', ['category' => 'dns']))->assertInertia(fn (Assert $page) => $page
            ->has('operations.data', 1)->where('operations.data.0.category', 'dns'));
    }

    public function test_admin_can_queue_only_existing_read_reconciliation_and_it_is_audited(): void
    {
        Queue::fake();
        [$admin, $customer] = $this->users();
        $order = $this->order($customer, 'domain_registration', 'provisioning', 'queue.example.com');
        $operation = $this->operation($customer, $order, 'domain_registration', 'ambiguous');

        $this->actingAs($admin)->post(route('admin.reconcile', ['registration', $operation->id]))->assertSessionHasNoErrors();

        Queue::assertPushed(ReconcileDomainRegistration::class, fn ($job) => $job->operationId === $operation->id);
        $this->assertDatabaseHas('admin_audit_logs', ['admin_user_id' => $admin->id, 'action' => 'registration.reconcile', 'resource_type' => 'registration', 'resource_id' => $operation->id]);
        $this->actingAs($customer)->post(route('admin.reconcile', ['registration', $operation->id]))->assertForbidden();
    }

    public function test_provider_configuration_is_safe_and_customer_pagination_is_server_side(): void
    {
        [$admin] = $this->users();
        config(['onlinenic.client_id' => null, 'onlinenic.password' => null, 'paymob.secret_key' => 'DO-NOT-SHOW', 'cloudflare.api_token' => 'DO-NOT-SHOW-EITHER']);
        User::factory()->count(26)->create();

        $this->actingAs($admin)->get(route('admin.providers'))->assertOk()->assertDontSee('DO-NOT-SHOW')->assertInertia(fn (Assert $page) => $page->where('providers.onlinenic.configured', false)->missing('providers.onlinenic.password'));
        $this->actingAs($admin)->get(route('admin.customers'))->assertInertia(fn (Assert $page) => $page->has('customers.data', 25)->where('customers.per_page', 25));
    }

    private function users(array $customer = []): array
    {
        return [User::factory()->create(['is_admin' => true]), User::factory()->create($customer)];
    }

    private function order(User $user, string $type, string $status, string $domain, array $extra = []): Order
    {
        return Order::create(array_merge(['user_id' => $user->id, 'type' => $type, 'status' => $status, 'domain' => $domain, 'tld' => 'com', 'registration_period' => 1, 'provider' => 'onlinenic', 'customer_price' => '10.00', 'currency' => 'USD', 'registration_data' => [], 'billing_data' => [], 'nameservers' => []], $extra));
    }

    private function domain(User $user, Order $order, string $name, mixed $expires): Domain
    {
        return Domain::create(['user_id' => $user->id, 'order_id' => $order->id, 'name' => $name, 'tld' => 'com', 'provider' => 'onlinenic', 'status' => 'active', 'expires_at' => $expires, 'nameservers' => []]);
    }

    private function operation(User $user, Order $order, string $name, string $status, ?string $cltrid = null): RegistrarOperation
    {
        return RegistrarOperation::create(['user_id' => $user->id, 'order_id' => $order->id, 'provider' => 'onlinenic', 'operation' => $name, 'cltrid' => $cltrid ?? fake()->uuid(), 'svtrid' => 'SVTRID-SECRET', 'status' => $status, 'provider_code' => 'PROVIDER-CODE-SECRET', 'provider_message' => 'RAW-XML-SECRET', 'started_at' => now(), 'completed_at' => $status === 'completed' ? now() : null]);
    }
}
