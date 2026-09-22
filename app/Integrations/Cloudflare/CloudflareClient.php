<?php

namespace App\Integrations\Cloudflare;

use App\Domain\Dns\Exceptions\AmbiguousDnsWrite;
use App\Domain\Dns\Exceptions\DnsConflict;
use App\Domain\Dns\Exceptions\DnsProviderException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

final class CloudflareClient
{
    public function request(string $method, string $path, array $data = []): array
    {
        $token = (string) config('cloudflare.api_token');
        if ($token === '') {
            throw new DnsProviderException('DNS provider is not configured.');
        }

        $write = ! in_array($method, ['GET'], true);
        try {
            $request = Http::withToken($token)->acceptJson()->timeout(12)->connectTimeout(5);
            $response = $method === 'GET'
                ? $request->get(rtrim((string) config('cloudflare.api_base'), '/').$path, $data)
                : $request->send($method, rtrim((string) config('cloudflare.api_base'), '/').$path, ['json' => $data]);
        } catch (ConnectionException) {
            throw $write ? new AmbiguousDnsWrite('DNS change needs confirmation.') : new DnsProviderException('DNS provider is unavailable.');
        }

        if ($response->serverError()) {
            throw $write ? new AmbiguousDnsWrite('DNS change needs confirmation.') : new DnsProviderException('DNS provider is unavailable.');
        }
        $body = $response->json();
        if (! is_array($body)) {
            throw $write ? new AmbiguousDnsWrite('DNS change needs confirmation.') : new DnsProviderException('DNS provider returned an invalid response.');
        }
        if ($response->failed() || ($body['success'] ?? false) !== true) {
            $code = $body['errors'][0]['code'] ?? null;
            if (in_array($code, [81053, 81057, 81058], true)) {
                throw new DnsConflict('This DNS record conflicts with another record at the same hostname.');
            }
            throw new DnsProviderException('DNS provider rejected the request.');
        }

        return $body;
    }
}
