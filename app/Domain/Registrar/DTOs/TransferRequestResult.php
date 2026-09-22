<?php

namespace App\Domain\Registrar\DTOs;

final readonly class TransferRequestResult
{
    public function __construct(public string $domain, public string $providerStatus, public string $status, public string $cltrid, public string $svtrid, public int $code) {}
}
