<?php

namespace App\Domain\Registrar\DTOs;

final readonly class DomainAvailability
{
    /** @param array<string, string|array<int, string>> $providerMetadata */
    public function __construct(
        public string $domain,
        public bool $available,
        public bool $premium = false,
        public bool $trademarkClaimRequired = false,
        public ?string $lookupKey = null,
        public array $providerMetadata = [],
    ) {}
}
