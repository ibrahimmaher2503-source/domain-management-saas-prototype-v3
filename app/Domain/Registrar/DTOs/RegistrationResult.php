<?php

namespace App\Domain\Registrar\DTOs;

final readonly class RegistrationResult
{
    /** @param array<string, string|array<int, string>> $providerMetadata */
    public function __construct(public string $domain, public ?string $registeredAt, public ?string $expiresAt, public string $cltrid, public string $svtrid, public int $providerCode, public string $providerMessage, public array $providerMetadata = []) {}
}
