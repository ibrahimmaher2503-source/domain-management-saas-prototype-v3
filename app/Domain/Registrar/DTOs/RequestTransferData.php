<?php

namespace App\Domain\Registrar\DTOs;

final readonly class RequestTransferData
{
    public function __construct(public string $domain) {}
}
