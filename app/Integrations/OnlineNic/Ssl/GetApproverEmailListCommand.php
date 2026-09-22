<?php

namespace App\Integrations\OnlineNic\Ssl;

final readonly class GetApproverEmailListCommand extends SslCommand
{
    public function __construct(string $domain)
    {
        parent::__construct('GetApproverEmailList', ['domain' => $domain]);
    }
}
