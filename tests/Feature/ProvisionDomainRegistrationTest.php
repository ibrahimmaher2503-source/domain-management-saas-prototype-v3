<?php

namespace Tests\Feature;

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
use App\Domain\Registrar\DTOs\TransferLockData;
use App\Domain\Registrar\DTOs\UpdateNameserversData;
use App\Integrations\OnlineNic\Exceptions\ProviderAmbiguousResponse;
use App\Integrations\OnlineNic\Exceptions\ProviderRejectedOperation;
use App\Integrations\OnlineNic\OnlineNicTransactionIdGenerator;
use App\Jobs\ProvisionDomainRegistration;
use App\Jobs\ReconcileDomainRegistration;
use App\Models\Domain;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ProvisionDomainRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'onlinenic.registrant_contact_id' => 'platform-r',
            'onlinenic.admin_contact_id' => 'platform-a',
            'onlinenic.tech_contact_id' => 'platform-t',
            'onlinenic.billing_contact_id' => 'platform-b',
        ]);
    }

    public function test_paid_order_registers_with_platform_ids_once_without_customer_contacts(): void
    {
        $order = $this->paidOrder();
        $fake = new PlatformRegistrarFake;

        $this->runJob($order, $fake);
        $this->runJob($order, $fake);

        $this->assertSame('completed', $order->fresh()->status);
        $this->assertSame(1, Domain::count());
        $this->assertSame(1, $fake->domainCalls);
        $this->assertSame(['registrant' => 'platform-r', 'administrative' => 'platform-a', 'technical' => 'platform-t', 'billing' => 'platform-b'], $fake->registration->contactIds);
        $this->assertSame(['ns1.example.net', 'ns2.example.net'], $fake->registration->nameservers);
        $this->assertSame(2, $fake->registration->period);
        $this->assertSame(0, $fake->registration->domainType);
        $this->assertSame(16, strlen($fake->registration->password));
        $this->assertNull($order->fresh()->provider_contact_ids);
        $this->assertNotSame($order->fresh()->domain_password, $order->fresh()->getRawOriginal('domain_password'));
        $this->assertSame(1, $order->registrarOperations()->count());
        $this->assertStringNotContainsString('platform-r', json_encode($order->registrarOperations()->first()->safe_request_metadata));
        $this->assertSame('2028-09-22', Domain::firstOrFail()->expires_at->toDateString());
    }

    public function test_missing_platform_contact_config_fails_closed_after_payment(): void
    {
        config(['onlinenic.tech_contact_id' => null]);
        $order = $this->paidOrder();
        $fake = new PlatformRegistrarFake;

        $this->runJob($order, $fake);

        $this->assertSame('failed', $order->fresh()->status);
        $this->assertSame('Registration requires a platform configuration review.', $order->fresh()->provisioning_failure_reason);
        $this->assertSame(0, $fake->domainCalls);
        $this->assertSame(0, $order->registrarOperations()->count());
        $this->assertSame('paid', Payment::firstOrFail()->status);
    }

    public function test_same_platform_id_can_fill_all_roles(): void
    {
        config(['onlinenic.registrant_contact_id' => 'shared', 'onlinenic.admin_contact_id' => 'shared', 'onlinenic.tech_contact_id' => 'shared', 'onlinenic.billing_contact_id' => 'shared']);
        $order = $this->paidOrder();
        $fake = new PlatformRegistrarFake;

        $this->runJob($order, $fake);

        $this->assertSame(['registrant' => 'shared', 'administrative' => 'shared', 'technical' => 'shared', 'billing' => 'shared'], $fake->registration->contactIds);
    }

    public function test_legacy_order_contact_ids_are_ignored(): void
    {
        $order = $this->paidOrder();
        $order->update(['provider_contact_ids' => ['registrant' => 'legacy-customer-id']]);
        $fake = new PlatformRegistrarFake;

        $this->runJob($order, $fake);

        $this->assertSame('platform-r', $fake->registration->contactIds['registrant']);
        $this->assertSame('legacy-customer-id', $order->fresh()->provider_contact_ids['registrant']);
    }

    public function test_unavailable_domain_does_not_register(): void
    {
        $order = $this->paidOrder();
        $fake = new PlatformRegistrarFake(available: false);

        $this->runJob($order, $fake);

        $this->assertSame('failed', $order->fresh()->status);
        $this->assertSame(0, $fake->domainCalls);
        $this->assertSame('paid', Payment::firstOrFail()->status);
    }

    public function test_ambiguous_domain_is_not_retried_and_can_be_reconciled_by_read(): void
    {
        $order = $this->paidOrder();
        $fake = new PlatformRegistrarFake(domainAmbiguous: true);
        $this->runJob($order, $fake);
        $this->runJob($order, $fake);
        $operation = $order->registrarOperations()->firstOrFail();

        $this->assertSame('ambiguous', $operation->status);
        $this->assertSame(1, $fake->domainCalls);
        (new ReconcileDomainRegistration($operation->id))->handle($fake);
        $this->assertSame(1, $fake->infoCalls);
        $this->assertSame('completed', $order->fresh()->status);
        $this->assertSame(1, Domain::count());
    }

    public function test_unresolved_reconciliation_stays_ambiguous(): void
    {
        $order = $this->paidOrder();
        $fake = new PlatformRegistrarFake(domainAmbiguous: true, infoRejected: true);
        $this->runJob($order, $fake);
        $operation = $order->registrarOperations()->firstOrFail();

        (new ReconcileDomainRegistration($operation->id))->handle($fake);

        $this->assertSame('ambiguous', $operation->fresh()->status);
        $this->assertSame('provisioning', $order->fresh()->status);
        $this->assertSame(0, Domain::count());
    }

    public function test_clear_rejection_keeps_payment_paid(): void
    {
        $order = $this->paidOrder();
        $fake = new PlatformRegistrarFake(domainRejected: true);

        $this->runJob($order, $fake);

        $this->assertSame('failed', $order->fresh()->status);
        $this->assertSame('failed', $order->registrarOperations()->first()->status);
        $this->assertSame('paid', Payment::firstOrFail()->status);
    }

    public function test_unpaid_and_non_com_orders_do_not_register(): void
    {
        $order = $this->paidOrder();
        $fake = new PlatformRegistrarFake;
        $order->payments()->delete();
        $this->runJob($order, $fake);
        $order->update(['domain' => 'example.net', 'tld' => 'net']);
        $this->runJob($order, $fake);

        $this->assertSame(0, $fake->domainCalls);
        $this->assertSame('paid', $order->fresh()->status);
    }

    private function runJob(Order $order, PlatformRegistrarFake $fake): void
    {
        (new ProvisionDomainRegistration($order->id))->handle($fake, app(OnlineNicTransactionIdGenerator::class));
    }

    private function paidOrder(): Order
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $order = Order::create(['user_id' => $user->id, 'type' => 'domain_registration', 'status' => 'paid', 'domain' => 'example.com', 'tld' => 'com', 'registration_period' => 2, 'provider' => 'onlinenic', 'provider_cost' => '8.59', 'customer_price' => '10.31', 'currency' => 'EGP', 'premium' => false, 'registration_data' => ['registrant' => ['name' => 'Legacy Customer']], 'nameservers' => ['ns1.example.net', 'ns2.example.net']]);
        Payment::create(['user_id' => $user->id, 'order_id' => $order->id, 'provider' => 'paymob', 'status' => 'paid', 'amount' => '10.31', 'currency' => 'EGP', 'provider_reference' => 'order-'.$order->id]);

        return $order;
    }
}

final class PlatformRegistrarFake implements RegistrarGateway
{
    public int $domainCalls = 0;

    public int $infoCalls = 0;

    public ?DomainRegistrationData $registration = null;

    public function __construct(private bool $available = true, private bool $domainAmbiguous = false, private bool $domainRejected = false, private bool $infoRejected = false) {}

    public function checkDomain(CheckDomainData $data): DomainAvailability
    {
        return new DomainAvailability($data->domain, $this->available);
    }

    public function getDomainPrice(DomainPriceQuery $query): DomainPrice
    {
        return new DomainPrice($query->domain, '8.59', $query->period);
    }

    public function registerDomain(DomainRegistrationData $data, string $cltrid): RegistrationResult
    {
        $this->domainCalls++;
        $this->registration = $data;
        if ($this->domainAmbiguous) {
            throw new ProviderAmbiguousResponse('ambiguous');
        }
        if ($this->domainRejected) {
            throw new ProviderRejectedOperation('rejected', 2001, 'rejected');
        }

        return new RegistrationResult($data->domain, '2026-09-22', '2028-09-22', $cltrid, 'srv-'.$cltrid, 1000, 'OK');
    }

    public function renewDomain(RenewDomainData $data, string $cltrid): RenewalResult
    {
        throw new \LogicException('Unexpected renewal.');
    }

    public function getDomainInfo(string $domain): DomainInfo
    {
        $this->infoCalls++;
        if ($this->infoRejected) {
            throw new ProviderRejectedOperation('not found', 2001, 'not found');
        }

        return new DomainInfo($domain, '2026-09-22', '2028-09-22', ['ns1.example.net', 'ns2.example.net'], 'active', null, 'info', 'srv-info', 1000, 'OK');
    }

    public function updateNameservers(UpdateNameserversData $data, string $cltrid): OperationResult
    {
        throw new \LogicException('Unexpected nameserver write.');
    }

    public function setTransferLock(TransferLockData $data, string $cltrid): OperationResult
    {
        throw new \LogicException('Unexpected write.');
    }

    public function getAuthCode(string $domain): AuthCodeResult
    {
        throw new \LogicException('Unexpected read.');
    }

    public function createContact(): never
    {
        throw new \LogicException('Customer registrar contacts must not be created.');
    }

    public function checkContact(): never
    {
        throw new \LogicException('Customer registrar contacts must not be checked.');
    }
}
