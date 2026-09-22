<?php

namespace App\Domain\Registrar\DTOs;

final readonly class RenewalResult
{
    public function __construct(public string $domain, public ?string $expiresAt, public string $cltrid, public string $svtrid, public int $providerCode, public string $providerMessage) {}
}
