<?php

namespace App\Integrations\OnlineNic\Ssl;

use App\Integrations\OnlineNic\Contracts\OnlineNicCommand;

readonly class SslCommand implements OnlineNicCommand
{
    public function __construct(private string $action, private array $params) {}

    public function category(): string
    {
        return 'ssl';
    }

    public function action(): string
    {
        return $this->action;
    }

    public function payload(): array
    {
        return $this->params;
    }
}
