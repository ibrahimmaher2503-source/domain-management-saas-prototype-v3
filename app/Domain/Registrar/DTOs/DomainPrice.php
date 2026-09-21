<?php

namespace App\Domain\Registrar\DTOs;

final readonly class DomainPrice
{
    public function __construct(
        public string $domain,
        public float $amount,
        public int $period,
        public string $operation = 'registration',
        public ?string $currency = null,
    ) {}
}
