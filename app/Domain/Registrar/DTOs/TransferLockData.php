<?php

namespace App\Domain\Registrar\DTOs;

final readonly class TransferLockData
{
    public function __construct(public string $domain, public bool $locked) {}
}
