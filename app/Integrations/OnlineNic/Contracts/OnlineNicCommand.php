<?php

namespace App\Integrations\OnlineNic\Contracts;

interface OnlineNicCommand
{
    public function category(): string;

    public function action(): string;

    /** @return array<string, scalar|null> */
    public function payload(): array;
}
