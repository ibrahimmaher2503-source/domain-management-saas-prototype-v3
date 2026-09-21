<?php

namespace App\Integrations\OnlineNic\Commands;

use App\Domain\Registrar\DTOs\CheckContactData;
use App\Integrations\OnlineNic\Contracts\OnlineNicCommand;

final readonly class CheckContactCommand implements OnlineNicCommand
{
    public function __construct(private CheckContactData $data) {}

    public function category(): string
    {
        return 'domain';
    }

    public function action(): string
    {
        return 'CheckContact';
    }

    public function payload(): array
    {
        return ['domaintype' => $this->data->domainType, 'contactid' => $this->data->contactId];
    }
}
