<?php

namespace Tests\Feature;

use App\Domain\Registrar\Contracts\RegistrarGateway;
use App\Domain\Registrar\DTOs\CheckContactData;
use App\Domain\Registrar\DTOs\CheckDomainData;
use App\Domain\Registrar\DTOs\ContactResult;
use App\Domain\Registrar\DTOs\CreateContactData;
use App\Domain\Registrar\DTOs\DomainAvailability;
use App\Domain\Registrar\DTOs\DomainInfo;
use App\Domain\Registrar\DTOs\DomainPrice;
use App\Domain\Registrar\DTOs\DomainPriceQuery;
use App\Domain\Registrar\DTOs\DomainRegistrationData;
use App\Domain\Registrar\DTOs\RegistrationResult;
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

    public function test_paid_order_creates_contacts_domain_and_completes_once(): void
    {
        [$user, $order] = $this->paidOrder();
        $fake = new ProvisioningFakeRegistrar;
        $this->app->instance(RegistrarGateway::class, $fake);

        (new ProvisionDomainRegistration($order->id))->handle($fake, app(OnlineNicTransactionIdGenerator::class));
        (new ProvisionDomainRegistration($order->id))->handle($fake, app(OnlineNicTransactionIdGenerator::class));

        $this->assertSame('completed', $order->fresh()->status);
        $this->assertSame(1, Domain::count());
        $this->assertSame(4, $fake->contactCalls);
        $this->assertSame(1, $fake->domainCalls);
        $this->assertSame(5, $order->registrarOperations()->where('status', 'completed')->count());
        $this->assertSame('contact-registrant', $order->fresh()->provider_contact_ids['registrant']);
        $this->assertStringNotContainsString('contact-registrant', $order->fresh()->getRawOriginal('provider_contact_ids'));
        $this->assertNotSame($order->fresh()->domain_password, $order->fresh()->getRawOriginal('domain_password'));
        $this->assertNull($order->registrarOperations()->first()->safe_request_metadata['password'] ?? null);
    }

    public function test_unavailable_domain_fails_registration_without_touching_contacts(): void
    {
        [, $order] = $this->paidOrder();
        $fake = new ProvisioningFakeRegistrar(available: false);
        $this->app->instance(RegistrarGateway::class, $fake);

        (new ProvisionDomainRegistration($order->id))->handle($fake, app(OnlineNicTransactionIdGenerator::class));

        $this->assertSame('failed', $order->fresh()->status);
        $this->assertSame('Domain became unavailable before registration.', $order->fresh()->provisioning_failure_reason);
        $this->assertSame(0, $fake->contactCalls);
        $this->assertSame(0, $fake->domainCalls);
        $this->assertSame('paid', Payment::firstOrFail()->status);
    }

    public function test_ambiguous_create_domain_is_not_retried(): void
    {
        [, $order] = $this->paidOrder();
        $fake = new ProvisioningFakeRegistrar(domainAmbiguous: true);
        $this->app->instance(RegistrarGateway::class, $fake);

        (new ProvisionDomainRegistration($order->id))->handle($fake, app(OnlineNicTransactionIdGenerator::class));
        (new ProvisionDomainRegistration($order->id))->handle($fake, app(OnlineNicTransactionIdGenerator::class));

        $this->assertSame('provisioning', $order->fresh()->status);
        $this->assertSame('ambiguous', $order->registrarOperations()->where('operation', 'domain_registration')->value('status'));
        $this->assertSame(1, $fake->domainCalls);
    }

    public function test_clear_domain_rejection_fails_order_but_keeps_payment_paid(): void
    {
        [, $order] = $this->paidOrder();
        $fake = new ProvisioningFakeRegistrar(domainRejected: true);
        $this->app->instance(RegistrarGateway::class, $fake);

        (new ProvisionDomainRegistration($order->id))->handle($fake, app(OnlineNicTransactionIdGenerator::class));

        $this->assertSame('failed', $order->fresh()->status);
        $this->assertSame('paid', Payment::firstOrFail()->status);
        $this->assertSame('failed', $order->registrarOperations()->where('operation', 'domain_registration')->value('status'));
    }

    public function test_non_com_paid_order_does_not_contact_registrar(): void
    {
        [, $order] = $this->paidOrder();
        $order->update(['domain' => 'example.net', 'tld' => 'net']);
        $fake = new ProvisioningFakeRegistrar;

        (new ProvisionDomainRegistration($order->id))->handle($fake, app(OnlineNicTransactionIdGenerator::class));

        $this->assertSame('paid', $order->fresh()->status);
        $this->assertSame(0, $fake->contactCalls);
        $this->assertSame(0, $fake->domainCalls);
    }

    public function test_unpaid_order_never_contacts_registrar(): void
    {
        [, $order] = $this->paidOrder();
        $order->payments()->delete();
        $fake = new ProvisioningFakeRegistrar;

        (new ProvisionDomainRegistration($order->id))->handle($fake, app(OnlineNicTransactionIdGenerator::class));

        $this->assertSame('paid', $order->fresh()->status);
        $this->assertSame(0, $fake->contactCalls);
    }

    public function test_ambiguous_contact_is_not_retried(): void
    {
        [, $order] = $this->paidOrder();
        $fake = new ProvisioningFakeRegistrar(contactAmbiguous: true);

        (new ProvisionDomainRegistration($order->id))->handle($fake, app(OnlineNicTransactionIdGenerator::class));
        (new ProvisionDomainRegistration($order->id))->handle($fake, app(OnlineNicTransactionIdGenerator::class));

        $this->assertSame(1, $fake->contactCalls);
        $this->assertSame(0, $fake->domainCalls);
        $this->assertSame('ambiguous', $order->registrarOperations()->first()->status);
        $this->assertSame('provisioning', $order->fresh()->status);
    }

    public function test_clear_contact_rejection_keeps_payment_paid(): void
    {
        [, $order] = $this->paidOrder();
        $fake = new ProvisioningFakeRegistrar(contactRejected: true);

        (new ProvisionDomainRegistration($order->id))->handle($fake, app(OnlineNicTransactionIdGenerator::class));

        $this->assertSame('failed', $order->fresh()->status);
        $this->assertSame('paid', Payment::firstOrFail()->status);
        $this->assertSame(0, $fake->domainCalls);
        $this->assertSame('failed', $order->registrarOperations()->first()->status);
    }

    public function test_ambiguous_domain_reconciliation_uses_info_without_repeating_registration(): void
    {
        [, $order] = $this->paidOrder();
        $fake = new ProvisioningFakeRegistrar(domainAmbiguous: true);

        (new ProvisionDomainRegistration($order->id))->handle($fake, app(OnlineNicTransactionIdGenerator::class));
        $operation = $order->registrarOperations()->where('operation', 'domain_registration')->firstOrFail();
        (new ReconcileDomainRegistration($operation->id))->handle($fake);

        $this->assertSame(1, $fake->domainCalls);
        $this->assertSame(1, $fake->infoCalls);
        $this->assertSame('completed', $operation->fresh()->status);
        $this->assertSame('completed', $order->fresh()->status);
        $this->assertSame(1, Domain::count());
    }

    public function test_unresolved_reconciliation_keeps_registration_ambiguous(): void
    {
        [, $order] = $this->paidOrder();
        $fake = new ProvisioningFakeRegistrar(domainAmbiguous: true, infoRejected: true);
        (new ProvisionDomainRegistration($order->id))->handle($fake, app(OnlineNicTransactionIdGenerator::class));
        $operation = $order->registrarOperations()->where('operation', 'domain_registration')->firstOrFail();

        (new ReconcileDomainRegistration($operation->id))->handle($fake);

        $this->assertSame('ambiguous', $operation->fresh()->status);
        $this->assertSame('provisioning', $order->fresh()->status);
        $this->assertSame(0, Domain::count());
    }

    /** @return array{0: User, 1: Order} */
    private function paidOrder(): array
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $contact = ['name' => 'Alice Example', 'organization' => 'Example Inc', 'country' => 'EG', 'province' => 'Cairo', 'city' => 'Cairo', 'street' => '1 Main Street', 'postal_code' => '11511', 'voice' => '+201000000000', 'fax' => '', 'email' => 'alice@example.com'];
        $order = Order::create(['user_id' => $user->id, 'type' => 'domain_registration', 'status' => 'paid', 'domain' => 'example.com', 'tld' => 'com', 'registration_period' => 2, 'provider' => 'onlinenic', 'provider_cost' => '8.59', 'customer_price' => '10.31', 'currency' => 'EGP', 'premium' => false, 'registration_data' => ['registrant' => $contact, 'administrative' => $contact, 'technical' => $contact, 'billing' => $contact], 'nameservers' => ['ns1.example.net', 'ns2.example.net']]);
        Payment::create(['user_id' => $user->id, 'order_id' => $order->id, 'provider' => 'paymob', 'status' => 'paid', 'amount' => '10.31', 'currency' => 'EGP', 'provider_reference' => 'order-'.$order->id]);

        return [$user, $order];
    }
}

final class ProvisioningFakeRegistrar implements RegistrarGateway
{
    public int $contactCalls = 0;

    public int $domainCalls = 0;

    public int $infoCalls = 0;

    public function __construct(private bool $available = true, private bool $domainAmbiguous = false, private bool $domainRejected = false, private bool $contactAmbiguous = false, private bool $infoRejected = false, private bool $contactRejected = false) {}

    public function checkDomain(CheckDomainData $data): DomainAvailability
    {
        return new DomainAvailability($data->domain, $this->available);
    }

    public function getDomainPrice(DomainPriceQuery $query): DomainPrice
    {
        return new DomainPrice($query->domain, '8.59', $query->period);
    }

    public function createContact(CreateContactData $data, string $cltrid): ContactResult
    {
        $this->contactCalls++;
        if ($this->contactAmbiguous) {
            throw new ProviderAmbiguousResponse('ambiguous');
        }
        if ($this->contactRejected) {
            throw new ProviderRejectedOperation('rejected', 2001, 'rejected');
        }

        return new ContactResult('contact-'.strtolower($data->name === 'Alice Example' ? ($this->contactCalls === 1 ? 'registrant' : (string) $this->contactCalls) : (string) $this->contactCalls), $cltrid, 'srv-'.$cltrid, 1000, 'OK');
    }

    public function checkContact(CheckContactData $data): bool
    {
        return true;
    }

    public function registerDomain(DomainRegistrationData $data, string $cltrid): RegistrationResult
    {
        $this->domainCalls++;
        if ($this->domainAmbiguous) {
            throw new ProviderAmbiguousResponse('ambiguous');
        } if ($this->domainRejected) {
            throw new ProviderRejectedOperation('rejected', 2001, 'rejected');
        }

        return new RegistrationResult($data->domain, '2026-09-22', '2028-09-22', $cltrid, 'srv-'.$cltrid, 1000, 'OK');
    }

    public function getDomainInfo(string $domain): DomainInfo
    {
        $this->infoCalls++;
        if ($this->infoRejected) {
            throw new ProviderRejectedOperation('not found', 2001, 'not found');
        }

        return new DomainInfo($domain, '2026-09-22', '2028-09-22', ['ns1.example.net', 'ns2.example.net'], 'active', 'info', 'srv-info', 1000, 'OK');
    }
}
