<?php

namespace App\Domain\Registrar\DTOs;

final readonly class OperationResult
{
    public function __construct(public string $cltrid, public string $svtrid, public int $providerCode, public string $providerMessage) {}
}
