<?php

namespace Tests\Unit;

use App\Domain\Domains\Services\CustomerDomainPricing;
use App\Domain\Domains\Services\DomainSearchService;
use App\Domain\Registrar\Contracts\RegistrarGateway;
use App\Domain\Registrar\DTOs\CheckDomainData;
use App\Domain\Registrar\DTOs\DomainAvailability;
use App\Domain\Registrar\DTOs\DomainPrice;
use App\Domain\Registrar\DTOs\DomainPriceQuery;
use App\Integrations\OnlineNic\Commands\CheckDomainCommand;
use App\Integrations\OnlineNic\Commands\GetDomainPriceCommand;
use App\Integrations\OnlineNic\Exceptions\UnsupportedCapability;
use App\Integrations\OnlineNic\OnlineNicTldResolver;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class DomainSearchServiceTest extends TestCase
{
    public function test_commands_use_documented_actions_and_payloads(): void
    {
        $check = new CheckDomainCommand('example.com', 0);
        $price = new GetDomainPriceCommand('example.com', 0, 1);

        $this->assertSame('CheckDomain', $check->action());
        $this->assertSame(['domaintype' => 0, 'domain' => 'example.com'], $check->payload());
        $this->assertSame('GetDomainPrice', $price->action());
        $this->assertSame(['domaintype' => 0, 'domain' => 'example.com', 'op' => 'reg', 'period' => 1], $price->payload());
    }

    public function test_search_normalizes_domain_and_separates_provider_and_customer_price(): void
    {
        $gateway = new FakeRegistrarGateway(new DomainAvailability('example.com', true), new DomainPrice('example.com', 8.59, 1));
        $result = (new DomainSearchService($gateway, new CustomerDomainPricing(20)))->search('  EXAMPLE.COM ');

        $this->assertSame('example.com', $result->availability->domain);
        $this->assertSame('8.59', $result->providerPrice?->amount);
        $this->assertSame('10.31', $result->customerPrice);
    }

    public function test_unavailable_domain_does_not_request_a_price(): void
    {
        $gateway = new FakeRegistrarGateway(new DomainAvailability('taken.com', false), null);
        $result = (new DomainSearchService($gateway, new CustomerDomainPricing))->search('taken.com');

        $this->assertNull($result->providerPrice);
        $this->assertNull($result->customerPrice);
        $this->assertFalse($result->availability->available);
    }

    public function test_invalid_domain_is_rejected_and_unknown_tld_is_not_guessed(): void
    {
        $service = new DomainSearchService(new FakeRegistrarGateway(new DomainAvailability('x.com', true), null), new CustomerDomainPricing);
        $this->expectException(InvalidArgumentException::class);
        $service->search('not a domain');
    }

    public function test_unknown_tld_is_not_guessed(): void
    {
        $this->expectException(UnsupportedCapability::class);
        (new OnlineNicTldResolver)->domainType('example.invalid');
    }
}

final class FakeRegistrarGateway implements RegistrarGateway
{
    public function __construct(private DomainAvailability $availability, private ?DomainPrice $price) {}

    public function checkDomain(CheckDomainData $data): DomainAvailability
    {
        return $this->availability;
    }

    public function getDomainPrice(DomainPriceQuery $query): DomainPrice
    {
        return $this->price ?? throw new InvalidArgumentException('Unexpected price request.');
    }
}
