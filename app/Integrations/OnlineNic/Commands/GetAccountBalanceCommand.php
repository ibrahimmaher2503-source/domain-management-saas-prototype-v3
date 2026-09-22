<?php

namespace App\Integrations\OnlineNic\Commands;

use App\Integrations\OnlineNic\Contracts\OnlineNicCommand;

final readonly class GetAccountBalanceCommand implements OnlineNicCommand
{
    public function category(): string
    {
        return 'account';
    }

    public function action(): string
    {
        return 'GetAccountBalance';
    }

    public function payload(): array
    {
        return [];
    }
}
