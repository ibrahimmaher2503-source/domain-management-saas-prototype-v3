<?php

namespace App\Domain\Registrar\DTOs;

final readonly class CheckDomainData
{
    public function __construct(public string $domain) {}
}
