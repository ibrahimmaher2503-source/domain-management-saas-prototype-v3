<?php

namespace App\Domain\Registrar\DTOs;

final readonly class ContactResult
{
    public function __construct(public string $contactId, public string $cltrid, public string $svtrid, public int $providerCode, public string $providerMessage) {}
}
