<?php

namespace App\Integrations\OnlineNic\Ssl;

final readonly class InfoCommand extends SslCommand
{
    public function __construct(string $orderId)
    {
        parent::__construct('Info', ['orderId' => $orderId]);
    }
}
