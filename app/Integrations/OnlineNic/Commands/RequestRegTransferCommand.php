<?php

namespace App\Integrations\OnlineNic\Commands;

use App\Domain\Registrar\DTOs\RequestTransferData;
use App\Integrations\OnlineNic\Contracts\OnlineNicCommand;

final readonly class RequestRegTransferCommand implements OnlineNicCommand
{
    public function __construct(private RequestTransferData $data, private int $domainType) {}

    public function category(): string
    {
        return 'domain';
    }

    public function action(): string
    {
        return 'RequestRegTransfer';
    }

    public function payload(): array
    {
        return ['domaintype' => $this->domainType, 'domain' => $this->data->domain, 'mailway' => 'Off'];
    }
}
