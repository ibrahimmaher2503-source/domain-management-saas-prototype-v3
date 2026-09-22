<?php

namespace App\Domain\Registrar\DTOs;

final readonly class DomainInfo
{
    /** @param array<int, string> $nameservers */
    public function __construct(public string $domain, public ?string $registeredAt, public ?string $expiresAt, public array $nameservers, public ?string $providerStatus, public ?bool $transferLocked, public string $cltrid, public string $svtrid, public int $providerCode, public string $providerMessage) {}
}
