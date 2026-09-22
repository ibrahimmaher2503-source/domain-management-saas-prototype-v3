<?php

namespace App\Domain\Domains\DTOs;

use App\Domain\Registrar\DTOs\DomainPrice;

final readonly class DomainRenewalQuote
{
    /** @param array<string, string> $billingData */
    public function __construct(public string $domain, public int $period, public DomainPrice $providerPrice, public string $customerPrice, public string $currency, public ?string $currentExpirationDate, public array $billingData) {}
}
