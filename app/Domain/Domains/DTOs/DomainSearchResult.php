<?php

namespace App\Domain\Domains\DTOs;

use App\Domain\Registrar\DTOs\DomainAvailability;
use App\Domain\Registrar\DTOs\DomainPrice;

final readonly class DomainSearchResult
{
    public function __construct(
        public DomainAvailability $availability,
        public ?DomainPrice $providerPrice,
        public ?float $customerPrice,
        public int $period,
    ) {}
}
