<?php

namespace App\Integrations\Paymob;

use App\Integrations\Paymob\DTOs\PaymobPaymentIntention;
use App\Integrations\Paymob\Exceptions\PaymentCreationFailed;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

final class PaymobClient
{
    /** @param array<string, mixed> $payload */
    public function createIntention(array $payload): PaymobPaymentIntention
    {
        if (! config('paymob.secret_key') || ! config('paymob.card_integration_id')) {
            throw new PaymentCreationFailed('Payment is temporarily unavailable.');
        }

        try {
            $response = Http::baseUrl((string) config('paymob.base_url'))
                ->withToken((string) config('paymob.secret_key'), 'Token')
                ->acceptJson()
                ->connectTimeout((float) config('paymob.connect_timeout'))
                ->timeout((float) config('paymob.timeout'))
                ->post('/v1/intention/', $payload);
        } catch (ConnectionException $exception) {
            throw new PaymentCreationFailed('Payment is temporarily unavailable.', previous: $exception);
        }

        $data = $response->json();
        if (! $response->successful() || ! is_array($data) || ! isset($data['id'], $data['intention_order_id'], $data['client_secret'])) {
            throw new PaymentCreationFailed('Payment is temporarily unavailable.');
        }

        return new PaymobPaymentIntention((string) $data['id'], (int) $data['intention_order_id'], (string) $data['client_secret'], (string) ($data['special_reference'] ?? ''), (string) ($data['status'] ?? 'created'));
    }
}
