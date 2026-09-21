<?php

namespace App\Domain\Registrar\DTOs;

final readonly class CheckContactData
{
    public function __construct(public string $contactId, public int $domainType = 0) {}
}
