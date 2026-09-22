<?php

namespace App\Integrations\OnlineNic\Commands;

use App\Domain\Registrar\DTOs\UpdateNameserversData;
use App\Integrations\OnlineNic\Contracts\OnlineNicCommand;

final readonly class UpdateDomainDnsCommand implements OnlineNicCommand
{
    public function __construct(private UpdateNameserversData $data, private int $domainType) {}

    public function category(): string
    {
        return 'domain';
    }

    public function action(): string
    {
        return 'UpdateDomainDns';
    }

    public function payload(): array
    {
        return ['domaintype' => $this->domainType, 'domain' => $this->data->domain, 'nameserver' => $this->data->nameservers];
    }
}
