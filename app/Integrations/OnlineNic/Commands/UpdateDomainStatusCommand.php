<?php

namespace App\Integrations\OnlineNic\Commands;

use App\Domain\Registrar\DTOs\TransferLockData;
use App\Integrations\OnlineNic\Contracts\OnlineNicCommand;

final readonly class UpdateDomainStatusCommand implements OnlineNicCommand
{
    public function __construct(private TransferLockData $data, private int $domainType) {}

    public function category(): string
    {
        return 'domain';
    }

    public function action(): string
    {
        return 'UpdateDomainStatus';
    }

    public function payload(): array
    {
        return ['domaintype' => $this->domainType, 'domain' => $this->data->domain, $this->data->locked ? 'addstatus' : 'remstatus' => 'clientTransferProhibited'];
    }
}
