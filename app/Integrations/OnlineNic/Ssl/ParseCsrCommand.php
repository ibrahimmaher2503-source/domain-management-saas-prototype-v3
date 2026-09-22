<?php

namespace App\Integrations\OnlineNic\Ssl;

final readonly class ParseCsrCommand extends SslCommand
{
    public function __construct(string $product, string $csr)
    {
        parent::__construct('ParseCSR', ['productCode' => $product, 'CSR' => $csr]);
    }
}
