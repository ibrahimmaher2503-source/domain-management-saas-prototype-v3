<?php

namespace App\Integrations\OnlineNic\Commands;

use App\Domain\Registrar\DTOs\RenewDomainData;
use App\Integrations\OnlineNic\Contracts\OnlineNicCommand;

final readonly class RenewDomainCommand implements OnlineNicCommand
{
    public function __construct(private RenewDomainData $data, private int $domainType) {}

    public function category(): string
    {
        return 'domain';
    }

    public function action(): string
    {
        return 'RenewDomain';
    }

    public function payload(): array
    {
        return ['domaintype' => $this->domainType, 'domain' => $this->data->domain, 'period' => $this->data->period];
    }
}
