<?php

namespace App\Integrations\OnlineNic\Exceptions;

use RuntimeException;

class OnlineNicException extends RuntimeException
{
    public function __construct(string $message, public readonly ?int $providerCode = null, public readonly ?string $providerMessage = null)
    {
        parent::__construct($message);
    }
}
