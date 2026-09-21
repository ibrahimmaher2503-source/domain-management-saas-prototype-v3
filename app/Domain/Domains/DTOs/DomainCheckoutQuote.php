<?php

namespace App\Domain\Domains\DTOs;

use App\Domain\Domains\Services\RegistrationCapabilityPolicy;
use App\Domain\Registrar\DTOs\DomainAvailability;
use App\Domain\Registrar\DTOs\DomainPrice;

final readonly class DomainCheckoutQuote
{
    public function __construct(public string $domain, public int $period, public DomainAvailability $availability, public DomainPrice $providerPrice, public string $customerPrice, public string $currency, public RegistrationCapabilityPolicy $capability) {}
}
