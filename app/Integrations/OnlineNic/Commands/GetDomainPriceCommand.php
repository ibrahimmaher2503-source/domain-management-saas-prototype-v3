<?php

namespace App\Integrations\OnlineNic\Commands;

use App\Integrations\OnlineNic\Contracts\OnlineNicCommand;

final readonly class GetDomainPriceCommand implements OnlineNicCommand
{
    public function __construct(private string $domain, private int $domainType, private string $operation, private int $period) {}

    public function category(): string
    {
        return 'domain';
    }

    public function action(): string
    {
        return 'GetDomainPrice';
    }

    public function payload(): array
    {
        return ['domaintype' => $this->domainType, 'domain' => $this->domain, 'op' => $this->operation, 'period' => $this->period];
    }
}
