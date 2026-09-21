<?php

namespace App\Integrations\OnlineNic;

final class OnlineNicTransactionIdGenerator
{
    public function generate(): string
    {
        return 'codex-'.bin2hex(random_bytes(12));
    }
}
