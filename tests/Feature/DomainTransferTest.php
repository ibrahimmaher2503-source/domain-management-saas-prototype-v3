<?php

namespace Tests\Feature;

use App\Domain\Domains\Services\CustomerDomainPricing;
use App\Domain\Domains\Services\DomainTransferService;
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
use App\Integrations\OnlineNic\OnlineNicTransactionIdGenerator;
use App\Jobs\DispatchPaidOrderFulfillment;
use App\Jobs\ProvisionDomainTransfer;
use App\Jobs\ReconcileDomainTransfer;
use App\Models\Domain;
use App\Models\Order;
use App\Models\Payment;
use App\Models\RegistrarOperation;
use App\Models\Transfer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class DomainTransferTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['onlinenic.account_currency' => 'EGP', 'onlinenic.customer_billing_currency' => 'EGP', 'domain.registration_markup_percent' => '20']);
    }

    public function test_quote_and_checkout_use_transfer_price_and_create_linked_records(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $fake = new TransferFakeRegistrar;
        $this->app->instance(RegistrarGateway::class, $fake);
        $quote = (new DomainTransferService($fake, new CustomerDomainPricing))->quote($user, 'Example.COM');
        $this->assertSame('transfer', $fake->priceQuery?->operation);
        $this->assertSame('10.31', $quote['customerPrice']);
        $this->actingAs($user)->post(route('transfers.store'), ['domain' => 'example.com', 'payment_billing' => $this->billing()])->assertRedirect();
        $order = Order::where('type', 'domain_transfer')->firstOrFail();
        $this->assertSame('awaiting_payment', $order->status);
        $this->assertSame($order->id, Transfer::firstOrFail()->order_id);
    }

    public function test_request_operation_precedes_single_async_write_and_ambiguity_never_resends(): void
    {
        Queue::fake();
        [$order, $transfer, $fake] = $this->paid();
        $fake->beforeRequest = fn () => $this->assertSame('pending', RegistrarOperation::where('operation', 'request_registrar_transfer')->firstOrFail()->status);
        $fake->requestMode = 'ambiguous';
        $job = new ProvisionDomainTransfer($order->id);
        $this->assertSame(1, $job->tries);
        $job->handle($fake, app(OnlineNicTransactionIdGenerator::class));
        $job->handle($fake, app(OnlineNicTransactionIdGenerator::class));
        $this->assertSame(1, $fake->requestCalls);
        $this->assertSame('ambiguous', $transfer->fresh()->status);
        Queue::assertPushed(ReconcileDomainTransfer::class);
    }

    public function test_generic_paid_fulfillment_routes_transfer(): void
    {
        Queue::fake();
        [$order] = $this->paid();
        (new DispatchPaidOrderFulfillment($order->id))->handle();
        Queue::assertPushed(ProvisionDomainTransfer::class, 1);
    }

    public function test_reconciliation_completes_and_creates_provider_synced_domain(): void
    {
        Queue::fake();
        [$order, $transfer, $fake] = $this->paid();
        (new ProvisionDomainTransfer($order->id))->handle($fake, app(OnlineNicTransactionIdGenerator::class));
        $fake->status = new TransferStatusResult('example.com', 'transferSuccessfully', 'completed');
        (new ReconcileDomainTransfer($transfer->id))->handle($fake);
        $domain = Domain::where('name', 'example.com')->firstOrFail();
        $this->assertSame($order->user_id, $domain->user_id);
        $this->assertNotNull($domain->provider_synced_at);
        $this->assertSame($domain->id, $transfer->fresh()->domain_id);
        $this->assertSame('completed', $order->fresh()->status);
    }

    public function test_ownership_conflict_is_never_reassigned(): void
    {
        Queue::fake();
        [$order, $transfer, $fake] = $this->paid();
        $owner = User::factory()->create();
        Domain::create(['user_id' => $owner->id, 'name' => 'example.com', 'tld' => 'com', 'provider' => 'onlinenic', 'status' => 'active', 'nameservers' => []]);
        $fake->status = new TransferStatusResult('example.com', 'transferSuccessfully', 'completed');
        (new ReconcileDomainTransfer($transfer->id))->handle($fake);
        $this->assertSame('action_required', $transfer->fresh()->status);
        $this->assertSame($owner->id, Domain::where('name', 'example.com')->value('user_id'));
    }

    public function test_cancel_requires_confirmation_and_current_pending_state_and_cross_user_is_hidden(): void
    {
        [$order, $transfer, $fake] = $this->paid();
        $transfer->update(['status' => 'pending']);
        $this->app->instance(RegistrarGateway::class, $fake);
        $other = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($other)->post(route('transfers.refresh', $transfer))->assertNotFound();
        $this->actingAs($order->user)->post(route('transfers.cancel', $transfer), ['confirmed' => false])->assertSessionHasErrors('confirmed');
        $this->actingAs($order->user)->post(route('transfers.cancel', $transfer), ['confirmed' => true])->assertRedirect();
        $this->assertSame(1, $fake->cancelCalls);
        $this->assertSame('cancelled', $transfer->fresh()->status);
        $this->assertSame('completed', RegistrarOperation::where('operation', 'cancel_registrar_transfer')->value('status'));
    }

    public function test_ambiguous_cancel_is_never_resubmitted(): void
    {
        Queue::fake();
        [$order, $transfer, $fake] = $this->paid();
        $transfer->update(['status' => 'pending']);
        $fake->cancelMode = 'ambiguous';
        $this->app->instance(RegistrarGateway::class, $fake);
        $this->actingAs($order->user)->post(route('transfers.cancel', $transfer), ['confirmed' => true])->assertRedirect();
        $this->actingAs($order->user)->post(route('transfers.cancel', $transfer), ['confirmed' => true])->assertRedirect();
        $this->assertSame(1, $fake->cancelCalls);
        $this->assertSame('ambiguous', RegistrarOperation::where('operation', 'cancel_registrar_transfer')->value('status'));
    }

    public function test_unresolved_reconciliation_is_bounded_and_read_only(): void
    {
        Queue::fake();
        [$order, $transfer, $fake] = $this->paid();
        $transfer->update(['status' => 'pending']);
        (new ReconcileDomainTransfer($transfer->id, 3))->handle($fake);
        Queue::assertNothingPushed();
        $this->assertSame(0, $fake->requestCalls);
        $this->assertSame('action_required', $transfer->fresh()->status);
    }

    private function paid(): array
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $order = Order::create(['user_id' => $user->id, 'type' => 'domain_transfer', 'status' => 'paid', 'domain' => 'example.com', 'tld' => 'com', 'registration_period' => 1, 'provider' => 'onlinenic', 'provider_cost' => '8.59', 'customer_price' => '10.31', 'currency' => 'EGP', 'premium' => false, 'registration_data' => null, 'billing_data' => $this->billing(), 'nameservers' => []]);
        Payment::create(['user_id' => $user->id, 'order_id' => $order->id, 'provider' => 'paymob', 'status' => 'paid', 'amount' => '10.31', 'currency' => 'EGP', 'provider_reference' => 'order-'.$order->id]);
        $transfer = Transfer::create(['user_id' => $user->id, 'order_id' => $order->id, 'domain' => 'example.com', 'tld' => 'com', 'provider' => 'onlinenic', 'direction' => 'in', 'status' => 'awaiting_payment']);

        return [$order, $transfer, new TransferFakeRegistrar];
    }

    private function billing(): array
    {
        return ['first_name' => 'Billing', 'last_name' => 'Customer', 'email' => 'payer@example.com', 'phone_number' => '+201000000000', 'country' => 'EG', 'city' => 'Cairo', 'street' => '1 Main', 'state' => 'Cairo', 'postal_code' => '11511'];
    }
}

final class TransferFakeRegistrar implements RegistrarGateway
{
    public ?DomainPriceQuery $priceQuery = null;

    public int $requestCalls = 0;

    public int $cancelCalls = 0;

    public string $requestMode = 'pending';

    public string $cancelMode = 'completed';

    public ?\Closure $beforeRequest = null;

    public TransferStatusResult $status;

    public function __construct()
    {
        $this->status = new TransferStatusResult('example.com', 'pendingTransfer', 'pending');
    }

    public function getDomainPrice(DomainPriceQuery $query): DomainPrice
    {
        $this->priceQuery = $query;

        return new DomainPrice($query->domain, '8.59', $query->period, $query->operation, 'EGP');
    }

    public function requestRegistrarTransfer(RequestTransferData $data, string $cltrid): TransferRequestResult
    {
        $this->requestCalls++;
        ($this->beforeRequest)?->__invoke();
        if ($this->requestMode === 'ambiguous') {
            throw new ProviderAmbiguousResponse('ambiguous');
        }

        return new TransferRequestResult($data->domain, 'pending', 'pending', $cltrid, 'server', 1001);
    }

    public function getRegistrarTransferStatus(string $domain): TransferStatusResult
    {
        return $this->status;
    }

    public function cancelRegistrarTransfer(string $domain, string $cltrid): OperationResult
    {
        $this->cancelCalls++;
        if ($this->cancelMode === 'ambiguous') {
            throw new ProviderAmbiguousResponse('ambiguous');
        }

        return new OperationResult($cltrid, 'server', 1000, 'OK');
    }

    public function getDomainInfo(string $domain): DomainInfo
    {
        return new DomainInfo($domain, '2025-09-22', '2027-09-22', ['ns1.example.net', 'ns2.example.net'], 'active', false, 'read', 'server', 1000, 'OK');
    }

    public function checkDomain(CheckDomainData $data): DomainAvailability
    {
        throw new \LogicException;
    }

    public function registerDomain(DomainRegistrationData $data, string $cltrid): RegistrationResult
    {
        throw new \LogicException;
    }

    public function renewDomain(RenewDomainData $data, string $cltrid): RenewalResult
    {
        throw new \LogicException;
    }

    public function updateNameservers(UpdateNameserversData $data, string $cltrid): OperationResult
    {
        throw new \LogicException;
    }

    public function setTransferLock(TransferLockData $data, string $cltrid): OperationResult
    {
        throw new \LogicException;
    }

    public function getAuthCode(string $domain): AuthCodeResult
    {
        throw new \LogicException;
    }
}
