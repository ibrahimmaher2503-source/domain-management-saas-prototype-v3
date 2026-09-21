<?php

namespace App\Domain\Registrar\DTOs;

final readonly class DomainPriceQuery
{
    public function __construct(
        public string $domain,
        public string $operation = 'registration',
        public int $period = 1,
    ) {}
}
