<?php

namespace App\Integrations\OnlineNic\Contracts;

interface OnlineNicTransport
{
    public function connect(): void;

    public function write(string $payload): void;

    public function read(): string;

    public function close(): void;
}
