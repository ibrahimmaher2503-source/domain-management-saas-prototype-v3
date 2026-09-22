<?php

namespace App\Integrations\OnlineNic\Commands;

use App\Integrations\OnlineNic\Contracts\OnlineNicCommand;

final readonly class CancelRegTransferCommand implements OnlineNicCommand
{
    public function __construct(private string $domain, private int $domainType) {}

    public function category(): string
    {
        return 'domain';
    }

    public function action(): string
    {
        return 'CancelRegTransfer';
    }

    public function payload(): array
    {
        return ['domaintype' => $this->domainType, 'domain' => $this->domain];
    }
}
