<?php

namespace App\Integrations\OnlineNic\Ssl;

use App\Domain\Ssl\Contracts\SslProvider;
use App\Integrations\OnlineNic\Exceptions\InvalidProviderResponse;
use App\Integrations\OnlineNic\Exceptions\ProviderAmbiguousResponse;
use App\Integrations\OnlineNic\OnlineNicClient;
use Carbon\Carbon;

final class OnlineNicSslProvider implements SslProvider
{
    public function __construct(private readonly OnlineNicClient $client) {}

    public function parseCsr(string $product, string $csr): array
    {
        $this->client->ensureAuthenticated();
        $response = $this->client->execute(new ParseCsrCommand($product, $csr));
        $domain = trim((string) ($response->data['DomainName'] ?? ''));
        if ($response->code !== 1000 || $domain === '') {
            throw new InvalidProviderResponse('OnlineNIC did not confirm the CSR.');
        }

        return ['domain' => strtolower($domain), 'country' => (string) ($response->data['Country'] ?? ''), 'organization' => (string) ($response->data['Organization'] ?? ''), 'state' => (string) ($response->data['State'] ?? ''), 'locality' => (string) ($response->data['Locality'] ?? '')];
    }

    public function getApproverEmails(string $domain): array
    {
        $this->client->ensureAuthenticated();
        $response = $this->client->execute(new GetApproverEmailListCommand($domain));
        if ($response->code !== 1000) {
            throw new InvalidProviderResponse('OnlineNIC did not confirm approver emails.');
        }
        $emails = [];
        foreach ($response->data as $key => $value) {
            if (preg_match('/^email\d+$/', $key) && is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $emails[] = $value;
            }
        }

        return array_values(array_unique($emails));
    }

    public function orderCertificate(array $request, string $transactionId): array
    {
        $this->client->ensureAuthenticated();
        $params = [
            'productCode' => $request['product'], 'validityPeriod' => $request['validity'],
            'webServerType' => $request['server'],
            'aFirstName' => $request['first_name'], 'aLastName' => $request['last_name'],
            'aPhone' => $request['phone'], 'aEmail' => $request['email'],
            'tFirstName' => $request['first_name'], 'tLastName' => $request['last_name'],
            'tPhone' => $request['phone'], 'tEmail' => $request['email'],
            'approverEmail' => $request['approver_email'], 'CSR' => $request['csr'],
            'renewalIndicator' => 'false',
        ];
        $response = $this->client->execute(new OrderCommand($params), $transactionId);
        $orderId = (string) ($response->data['orderId'] ?? '');
        if ($orderId === '') {
            throw new ProviderAmbiguousResponse('OnlineNIC accepted SSL Order without a usable order ID.');
        }
        $price = $response->data['price'] ?? null;

        return ['order_id' => $orderId, 'price' => is_string($price) && preg_match('/^\d+(?:\.\d{1,2})?$/', $price) ? $price : null];
    }

    public function getCertificateOrder(string $orderId): array
    {
        $this->client->ensureAuthenticated();
        $response = $this->client->execute(new InfoCommand($orderId));
        if ($response->code !== 1000 || (string) ($response->data['orderId'] ?? '') !== $orderId) {
            throw new InvalidProviderResponse('OnlineNIC did not confirm the SSL order.');
        }
        $raw = strtoupper(trim((string) ($response->data['status'] ?? '')));
        $status = match ($raw) {
            'COMPLETE' => 'issued',
            'PENDING' => 'pending_validation',
            'PRE' => 'processing',
            'CANCELLED' => 'cancelled',
            'REFUND' => 'failed',
            default => 'action_required',
        };
        $issued = $response->data['orderCompleteDate'] ?? null;
        $expires = $response->data['expire'] ?? null;

        return ['status' => $status, 'provider_status' => $raw, 'issued_at' => $status === 'issued' && is_string($issued) ? Carbon::parse($issued)->toDateTimeString() : null, 'expires_at' => $status === 'issued' && is_string($expires) ? Carbon::createFromFormat('m/d/Y', $expires)->toDateString() : null];
    }
}
