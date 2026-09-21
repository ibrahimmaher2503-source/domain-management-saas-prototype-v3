<?php

namespace App\Integrations\OnlineNic\Commands;

use App\Domain\Registrar\DTOs\CreateContactData;
use App\Integrations\OnlineNic\Contracts\OnlineNicCommand;

final readonly class CreateContactCommand implements OnlineNicCommand
{
    public function __construct(private CreateContactData $data) {}

    public function category(): string
    {
        return 'domain';
    }

    public function action(): string
    {
        return 'CreateContact';
    }

    public function payload(): array
    {
        return ['domaintype' => $this->data->domainType, 'name' => $this->data->name, 'org' => $this->data->organization, 'country' => $this->data->country, 'province' => $this->data->province, 'city' => $this->data->city, 'street' => $this->data->street, 'postalcode' => $this->data->postalCode, 'voice' => $this->data->voice, 'fax' => $this->data->fax, 'email' => $this->data->email, 'password' => $this->data->password];
    }
}
