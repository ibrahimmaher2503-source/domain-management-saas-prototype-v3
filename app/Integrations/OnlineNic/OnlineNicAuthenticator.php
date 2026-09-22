<?php

namespace App\Integrations\OnlineNic;

final class OnlineNicAuthenticator
{
    public function __construct(private readonly string $clientId, private readonly string $password) {}

    /** @param array<string, scalar|array<int, scalar>|null> $payload */
    public function requestChecksum(string $transactionId, string $action, array $payload = []): string
    {
        $documentedAction = $action;
        $action = strtolower($action);
        if ($action === 'createcontact') {
            $action = 'crtcontact';
            $values = implode('', array_map(static fn ($key): string => (string) ($payload[$key] ?? ''), ['name', 'org', 'email']));
        } elseif ($action === 'createdomain') {
            $dns = $payload['dns'] ?? [];
            $values = implode('', array_map(static fn ($key): string => (string) ($payload[$key] ?? ''), ['domaintype', 'domain', 'period']))
                .(string) ($dns[0] ?? '').(string) ($dns[1] ?? '')
                .implode('', array_map(static fn ($key): string => (string) ($payload[$key] ?? ''), ['registrant', 'admin', 'tech', 'billing', 'password']));
        } elseif ($action === 'parsecsr') {
            $values = (string) ($payload['productCode'] ?? '').(string) ($payload['CSR'] ?? '');
        } elseif (in_array($action, ['cancel', 'changeapproveremail', 'resendapproveremail', 'reissue', 'resendfulfillmentemail'], true)) {
            $values = (string) ($payload['orderId'] ?? '');
        } elseif (in_array($action, ['updatedomaindns', 'updatedomainstatus', 'requestregtransfer', 'queryregtransfer', 'cancelregtransfer'], true)) {
            $values = (string) ($payload['domaintype'] ?? '').(string) ($payload['domain'] ?? '');
        } elseif ($action === 'renewdomain') {
            $values = (string) ($payload['domaintype'] ?? '').(string) ($payload['domain'] ?? '').(string) ($payload['period'] ?? '');
        } else {
            $values = in_array($action, ['login', 'logout'], true)
                ? ''
                : implode('', array_map(static fn ($value): string => is_array($value) ? implode('', array_map('strval', $value)) : (string) $value, $payload));
        }

        $sslActions = ['order', 'info', 'getapproveremaillist', 'parsecsr', 'cancel', 'changeapproveremail', 'resendapproveremail', 'reissue', 'resendfulfillmentemail'];
        $checksumAction = in_array($action, $sslActions, true) ? $documentedAction : $action;

        return md5($this->clientId.md5($this->password).$transactionId.$checksumAction.$values);
    }

    public function responseChecksum(string $transactionId, string $serverTransactionId, string|int $code, string $message, string $value = ''): string
    {
        return md5($this->clientId.md5($this->password).$transactionId.$serverTransactionId.$code.$message.$value);
    }
}
