<?php

namespace App\Domain\Registrar\DTOs;

final readonly class RenewDomainData
{
    public function __construct(public string $domain, public int $period) {}
}
