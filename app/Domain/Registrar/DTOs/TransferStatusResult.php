<?php

namespace App\Domain\Registrar\DTOs;

final readonly class TransferStatusResult
{
    public function __construct(public string $domain, public string $providerStatus, public string $status) {}
}
