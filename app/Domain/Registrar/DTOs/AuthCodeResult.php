<?php

namespace App\Domain\Registrar\DTOs;

final readonly class AuthCodeResult
{
    public function __construct(
        public string $authCode,
        public string $cltrid,
        public string $svtrid,
        public int $providerCode,
    ) {}
}
