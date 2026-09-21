<?php

namespace App\Integrations\OnlineNic\Commands;

use App\Domain\Registrar\DTOs\DomainRegistrationData;
use App\Integrations\OnlineNic\Contracts\OnlineNicCommand;

final readonly class CreateDomainCommand implements OnlineNicCommand
{
    public function __construct(private DomainRegistrationData $data) {}

    public function category(): string
    {
        return 'domain';
    }

    public function action(): string
    {
        return 'CreateDomain';
    }

    public function payload(): array
    {
        return ['domaintype' => $this->data->domainType, 'mltype' => 0, 'domain' => $this->data->domain, 'period' => $this->data->period, 'dns' => $this->data->nameservers, 'registrant' => $this->data->contactIds['registrant'], 'tech' => $this->data->contactIds['technical'], 'billing' => $this->data->contactIds['billing'], 'admin' => $this->data->contactIds['administrative'], 'password' => $this->data->password];
    }
}
