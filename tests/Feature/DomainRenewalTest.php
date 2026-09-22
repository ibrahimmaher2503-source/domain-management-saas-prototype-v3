<?php

namespace Tests\Feature;

use App\Domain\Domains\Exceptions\CheckoutUnavailable;
use App\Domain\Domains\Services\CustomerDomainPricing;
use App\Domain\Domains\Services\DomainRenewalQuoteService;
use App\Domain\Domains\Services\SyncDomainFromRegistrar;
use App\Domain\Registrar\Contracts\RegistrarGateway;
use App\Domain\Registrar\DTOs\AuthCodeResult;
use App\Domain\Registrar\DTOs\CheckDomainData;
use App\Domain\Registrar\DTOs\DomainAvailability;
use App\Domain\Registrar\DTOs\DomainInfo;
use App\Domain\Registrar\DTOs\DomainPrice;
use App\Domain\Registrar\DTOs\DomainPriceQuery;
use App\Domain\Registrar\DTOs\DomainRegistrationData;
use App\Domain\Registrar\DTOs\OperationResult;
use App\Domain\Registrar\DTOs\RegistrationResult;
use App\Domain\Registrar\DTOs\RenewalResult;
use App\Domain\Registrar\DTOs\RenewDomainData;
use App\Domain\Registrar\DTOs\RequestTransferData;
use App\Domain\Registrar\DTOs\TransferLockData;
use App\Domain\Registrar\DTOs\TransferRequestResult;
use App\Domain\Registrar\DTOs\TransferStatusResult;
use App\Domain\Registrar\DTOs\UpdateNameserversData;
use App\Integrations\OnlineNic\Exceptions\ProviderAmbiguousResponse;
use App\Integrations\OnlineNic\Exceptions\ProviderRejectedOperation;
use App\Integrations\OnlineNic\OnlineNicTransactionIdGenerator;
use App\Jobs\DispatchPaidOrderFulfillment;
use App\Jobs\ProvisionDomainRegistration;
use App\Jobs\ProvisionDomainRenewal;
use App\Jobs\ReconcileDomainRenewal;
use App\Models\Domain;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class DomainRenewalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['onlinenic.account_currency' => 'EGP', 'onlinenic.customer_billing_currency' => 'EGP', 'domain.registration_markup_percent' => '20']);
    }

    public function test_quote_uses_renewal_price_and_decimal_customer_pricing(): void
    {
        [$user, $domain, $fake] = $this->domain();
        $quote = (new DomainRenewalQuoteService($fake, new CustomerDomainPricing))->quote($user, $domain, 2);

        $this->assertSame('renewal', $fake->priceQuery->operation);
        $this->assertSame(2, $fake->priceQuery->period);
        $this->assertSame('8.59', $quote->providerPrice->amount);
        $this->assertSame('10.31', $quote->customerPrice);
        $this->assertSame('2027-09-22', $quote->currentExpirationDate);
    }

    public function test_eligibility_period_ownership_and_currency_fail_closed(): void
    {
        [$user, $domain, $fake] = $this->domain();
        $service = new DomainRenewalQuoteService($fake, new CustomerDomainPricing);
        foreach ([0, 11] as $period) {
            try {
                $service->quote($user, $domain, $period);
                $this->fail();
            } catch (CheckoutUnavailable) {
                $this->assertTrue(true);
            }
        }
        $other = User::factory()->create();
        try {
            $service->quote($other, $domain, 1);
            $this->fail();
        } catch (CheckoutUnavailable) {
            $this->assertTrue(true);
        }
        $domain->update(['tld' => 'net']);
        try {
            $service->quote($user, $domain->fresh(), 1);
            $this->fail();
        } catch (CheckoutUnavailable) {
            $this->assertTrue(true);
        }
        $domain->update(['tld' => 'com']);
        config(['onlinenic.customer_billing_currency' => 'USD']);
        try {
            $service->quote($user, $domain->fresh(), 1);
            $this->fail();
        } catch (CheckoutUnavailable) {
            $this->assertTrue(true);
        }
    }

    public function test_renewal_page_prefills_billing_and_creates_one_encrypted_linked_order(): void
    {
        [$user, $domain] = $this->domain();
        Order::create([...$this->orderData($user, $domain), 'type' => 'domain_registration', 'status' => 'completed', 'domain_id' => null]);
        $billing = $this->billing();

        $this->actingAs($user)->get(route('domains.show', ['domain' => $domain, 'tab' => 'renewal']))->assertInertia(fn (Assert $page) => $page->component('Domains/Show')->where('initialTab', 'Renewal')->where('renewalQuote.customerPrice', '10.31')->where('renewalQuote.billingData.email', 'payer@example.com'));
        $this->assertSame(0, Order::where('type', 'domain_renewal')->count());
        $this->actingAs($user)->post(route('domains.renewal.store', $domain), ['period' => 2, 'payment_billing' => $billing])->assertRedirect();
        $order = Order::where('type', 'domain_renewal')->firstOrFail();
        $this->assertSame($domain->id, $order->domain_id);
        $this->assertSame('awaiting_payment', $order->status);
        $this->assertNotSame(json_encode($billing), $order->getRawOriginal('billing_data'));
        $this->actingAs($user)->post(route('domains.renewal.store', $domain), ['period' => 1, 'payment_billing' => $billing])->assertSessionHasErrors('renewal');
        $this->assertSame(1, Order::where('type', 'domain_renewal')->count());
    }

    public function test_paid_fulfillment_routes_registration_renewal_and_unknown_closed(): void
    {
        Queue::fake();
        [$user, $domain] = $this->domain();
        foreach (['domain_registration', 'domain_renewal', 'unknown'] as $type) {
            $order = Order::create([...$this->orderData($user, $domain), 'type' => $type, 'status' => 'paid', 'domain_id' => $type === 'domain_renewal' ? $domain->id : null]);
            Payment::create(['user_id' => $user->id, 'order_id' => $order->id, 'provider' => 'paymob', 'status' => 'paid', 'amount' => '10.31', 'currency' => 'EGP', 'provider_reference' => 'order-'.$order->id]);
            (new DispatchPaidOrderFulfillment($order->id))->handle();
            if ($type === 'unknown') {
                $this->assertSame('failed', $order->fresh()->status);
            }
        }
        Queue::assertPushed(ProvisionDomainRegistration::class, 1);
        Queue::assertPushed(ProvisionDomainRenewal::class, 1);
    }

    public function test_success_records_operation_before_write_and_updates_expiration(): void
    {
        [$order, $fake] = $this->paidRenewal();
        $fake->beforeRenew = function () use ($order): void {
            $operation = $order->registrarOperations()->where('operation', 'domain_renewal')->firstOrFail();
            self::assertSame('pending', $operation->status);
            self::assertSame('2027-09-22', $operation->safe_request_metadata['expires_at_before']);
        };

        $this->runRenewal($order, $fake);

        $this->assertSame(1, $fake->renewCalls);
        $this->assertSame('completed', $order->fresh()->status);
        $this->assertSame('2029-09-22', $order->renewalDomain->fresh()->expires_at->toDateString());
        $this->assertSame('completed', $order->registrarOperations()->where('operation', 'domain_renewal')->value('status'));
        $this->assertSame('paid', $order->payments()->first()->status);
        $this->actingAs($order->user)->get(route('domains.show', $order->domain_id))->assertInertia(fn (Assert $page) => $page->where('domain.expires_at', '2029-09-22'));
    }

    public function test_clear_rejection_fails_order_but_keeps_payment_paid(): void
    {
        [$order, $fake] = $this->paidRenewal();
        $fake->renewMode = 'rejected';
        $this->runRenewal($order, $fake);
        $this->assertSame('failed', $order->fresh()->status);
        $this->assertSame('failed', $order->registrarOperations()->where('operation', 'domain_renewal')->value('status'));
        $this->assertSame('paid', $order->payments()->first()->status);
    }

    public function test_confirmed_renewal_completes_from_response_when_followup_sync_fails(): void
    {
        [$order, $fake] = $this->paidRenewal();
        $fake->failInfoAfterRenew = true;
        $this->runRenewal($order, $fake);

        $this->assertSame('completed', $order->fresh()->status);
        $this->assertSame('completed', $order->registrarOperations()->where('operation', 'domain_renewal')->value('status'));
        $this->assertSame('2029-09-22', $order->renewalDomain->fresh()->expires_at->toDateString());
        $this->assertSame(1, $fake->renewCalls);
    }

    public function test_other_user_cannot_quote_create_pay_or_view_renewal(): void
    {
        [$user, $domain] = $this->domain();
        $order = Order::create([...$this->orderData($user, $domain), 'type' => 'domain_renewal', 'status' => 'awaiting_payment', 'domain_id' => $domain->id]);
        $other = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($other)->get(route('domains.show', ['domain' => $domain, 'tab' => 'renewal']))->assertNotFound();
        $this->actingAs($other)->post(route('domains.renewal.store', $domain), ['period' => 1, 'payment_billing' => $this->billing()])->assertNotFound();
        $this->actingAs($other)->post(route('orders.pay', $order))->assertNotFound();
        $this->actingAs($other)->get(route('orders.show', $order))->assertNotFound();
    }

    public function test_ambiguous_write_is_never_repeated_and_read_can_confirm_it(): void
    {
        Queue::fake();
        [$order, $fake] = $this->paidRenewal();
        $fake->renewMode = 'ambiguous';
        $this->runRenewal($order, $fake);
        $this->runRenewal($order, $fake);
        $operation = $order->registrarOperations()->where('operation', 'domain_renewal')->firstOrFail();
        $this->assertSame(1, $fake->renewCalls);
        $this->assertSame('ambiguous', $operation->status);
        $fake->expiresAt = '2029-09-22';
        (new ReconcileDomainRenewal($operation->id))->handle($fake);
        $this->assertSame('completed', $order->fresh()->status);
        $this->assertSame('2029-09-22', $order->renewalDomain->fresh()->expires_at->toDateString());
    }

    public function test_unresolved_reconciliation_is_bounded_and_read_only(): void
    {
        Queue::fake();
        [$order, $fake] = $this->paidRenewal();
        $fake->renewMode = 'ambiguous';
        $this->runRenewal($order, $fake);
        $operation = $order->registrarOperations()->where('operation', 'domain_renewal')->firstOrFail();
        Queue::fake();
        (new ReconcileDomainRenewal($operation->id, 3))->handle($fake);
        Queue::assertNothingPushed();
        $this->assertSame(1, $fake->renewCalls);
        $this->assertSame('ambiguous', $operation->fresh()->status);
        $this->assertSame('provisioning', $order->fresh()->status);
    }

    private function runRenewal(Order $order, RenewalFakeRegistrar $fake): void
    {
        $this->app->instance(RegistrarGateway::class, $fake);
        (new ProvisionDomainRenewal($order->id))->handle($fake, app(SyncDomainFromRegistrar::class), app(OnlineNicTransactionIdGenerator::class));
    }

    private function paidRenewal(): array
    {
        [$user, $domain, $fake] = $this->domain();
        $order = Order::create([...$this->orderData($user, $domain), 'type' => 'domain_renewal', 'status' => 'paid', 'domain_id' => $domain->id]);
        Payment::create(['user_id' => $user->id, 'order_id' => $order->id, 'provider' => 'paymob', 'status' => 'paid', 'amount' => '10.31', 'currency' => 'EGP', 'provider_reference' => 'order-'.$order->id]);

        return [$order, $fake];
    }

    private function domain(): array
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $domain = Domain::create(['user_id' => $user->id, 'name' => 'example.com', 'tld' => 'com', 'provider' => 'onlinenic', 'status' => 'active', 'registered_at' => '2026-09-22', 'expires_at' => '2027-09-22', 'nameservers' => ['ns1.example.net', 'ns2.example.net']]);
        $fake = new RenewalFakeRegistrar;
        $this->app->instance(RegistrarGateway::class, $fake);

        return [$user, $domain, $fake];
    }

    private function orderData(User $user, Domain $domain): array
    {
        return ['user_id' => $user->id, 'domain' => $domain->name, 'tld' => 'com', 'registration_period' => 2, 'provider' => 'onlinenic', 'provider_cost' => '8.59', 'customer_price' => '10.31', 'currency' => 'EGP', 'premium' => false, 'registration_data' => null, 'billing_data' => $this->billing(), 'nameservers' => $domain->nameservers];
    }

    private function billing(): array
    {
        return ['first_name' => 'Billing', 'last_name' => 'Customer', 'email' => 'payer@example.com', 'phone_number' => '+201000000000', 'country' => 'EG', 'city' => 'Cairo', 'street' => '1 Main', 'state' => 'Cairo', 'postal_code' => '11511'];
    }
}

final class RenewalFakeRegistrar implements RegistrarGateway
{
    public ?DomainPriceQuery $priceQuery = null;

    public int $renewCalls = 0;

    public string $renewMode = 'completed';

    public string $expiresAt = '2027-09-22';

    public ?\Closure $beforeRenew = null;

    public bool $failInfoAfterRenew = false;

    public function checkDomain(CheckDomainData $data): DomainAvailability
    {
        throw new \LogicException;
    }

    public function getDomainPrice(DomainPriceQuery $query): DomainPrice
    {
        $this->priceQuery = $query;

        return new DomainPrice($query->domain, '8.59', $query->period, $query->operation, 'EGP');
    }

    public function registerDomain(DomainRegistrationData $data, string $cltrid): RegistrationResult
    {
        throw new \LogicException;
    }

    public function renewDomain(RenewDomainData $data, string $cltrid): RenewalResult
    {
        $this->renewCalls++;
        ($this->beforeRenew)?->__invoke();
        if ($this->renewMode === 'ambiguous') {
            throw new ProviderAmbiguousResponse('ambiguous');
        }
        if ($this->renewMode === 'rejected') {
            throw new ProviderRejectedOperation('rejected', 2001, 'private');
        }
        $this->expiresAt = Carbon::parse($this->expiresAt)->addYearsNoOverflow($data->period)->toDateString();

        return new RenewalResult($data->domain, $this->expiresAt, $cltrid, 'server', 1000, 'OK');
    }

    public function getDomainInfo(string $domain): DomainInfo
    {
        if ($this->failInfoAfterRenew && $this->renewCalls > 0) {
            throw new ProviderRejectedOperation('unavailable', 2001, 'private');
        }

        return new DomainInfo($domain, '2026-09-22', $this->expiresAt, ['ns1.example.net', 'ns2.example.net'], 'active', null, 'read', 'server', 1000, 'OK');
    }

    public function updateNameservers(UpdateNameserversData $data, string $cltrid): OperationResult
    {
        throw new \LogicException;
    }

    public function setTransferLock(TransferLockData $data, string $cltrid): OperationResult
    {
        throw new \LogicException;
    }

    public function requestRegistrarTransfer(RequestTransferData $data, string $cltrid): TransferRequestResult
    {
        throw new \LogicException('Unexpected transfer write.');
    }

    public function getRegistrarTransferStatus(string $domain): TransferStatusResult
    {
        throw new \LogicException('Unexpected transfer read.');
    }

    public function cancelRegistrarTransfer(string $domain, string $cltrid): OperationResult
    {
        throw new \LogicException('Unexpected transfer write.');
    }

    public function getAuthCode(string $domain): AuthCodeResult
    {
        throw new \LogicException;
    }
}
