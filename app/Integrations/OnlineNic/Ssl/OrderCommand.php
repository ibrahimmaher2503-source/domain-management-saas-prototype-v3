<?php

namespace App\Integrations\OnlineNic\Ssl;

final readonly class OrderCommand extends SslCommand
{
    public function __construct(array $params)
    {
        parent::__construct('Order', $params);
    }
}
