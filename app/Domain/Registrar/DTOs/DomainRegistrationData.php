<?php

namespace App\Domain\Registrar\DTOs;

final readonly class DomainRegistrationData
{
    /** @param array<int, string> $nameservers */
    /** @param array<string, string> $contactIds */
    public function __construct(public string $domain, public int $period, public array $nameservers, public array $contactIds, public string $password, public int $domainType = 0) {}
}
