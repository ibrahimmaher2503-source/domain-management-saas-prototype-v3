<?php

namespace App\Integrations\OnlineNic;

final readonly class OnlineNicResponse
{
    /** @param array<string, string|array<int, string>> $data */
    public function __construct(
        public int $code,
        public string $message,
        public string $cltrid,
        public string $svtrid,
        public array $data,
        public string $providerStatus,
    ) {}

    public function successful(): bool
    {
        return in_array($this->code, [1000, 1001, 1300, 1301], true);
    }
}
