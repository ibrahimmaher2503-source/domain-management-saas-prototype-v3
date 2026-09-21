<?php

namespace App\Integrations\OnlineNic\Commands;

use App\Integrations\OnlineNic\Contracts\OnlineNicCommand;

final readonly class InfoDomainCommand implements OnlineNicCommand
{
    public function __construct(private string $domain, private int $domainType = 0) {}

    public function category(): string
    {
        return 'domain';
    }

    public function action(): string
    {
        return 'InfoDomain';
    }

    public function payload(): array
    {
        return ['domaintype' => $this->domainType, 'domain' => $this->domain];
    }
}
