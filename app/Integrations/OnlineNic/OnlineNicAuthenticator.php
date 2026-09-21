<?php

namespace App\Integrations\OnlineNic;

final class OnlineNicAuthenticator
{
    public function __construct(private readonly string $clientId, private readonly string $password) {}

    /** @param array<string, scalar|null> $payload */
    public function requestChecksum(string $transactionId, string $action, array $payload = []): string
    {
        $values = in_array(strtolower($action), ['login', 'logout'], true)
            ? ''
            : implode('', array_map(static fn ($value): string => (string) $value, $payload));

        return md5($this->clientId.md5($this->password).$transactionId.strtolower($action).$values);
    }

    public function responseChecksum(string $transactionId, string $serverTransactionId, string|int $code, string $message, string $value = ''): string
    {
        return md5($this->clientId.md5($this->password).$transactionId.$serverTransactionId.$code.$message.$value);
    }
}
