<?php

namespace App\Integrations\Paymob;

use App\Domain\Billing\Contracts\PaymentGateway;
use App\Domain\Billing\DTOs\PaymentSession;
use App\Domain\Billing\Money;
use App\Integrations\Paymob\Exceptions\PaymentCreationFailed;
use App\Models\Order;

final class PaymobPaymentGateway implements PaymentGateway
{
    public function __construct(private readonly PaymobClient $client) {}

    public function createPayment(Order $order): PaymentSession
    {
        $contact = data_get($order->registration_data, 'registrant', []);
        $reference = 'order-'.$order->id;
        $firstName = trim(explode(' ', (string) ($contact['name'] ?? ''), 2)[0] ?? '');
        $lastName = trim(explode(' ', (string) ($contact['name'] ?? ''), 2)[1] ?? $firstName);
        $notification = config('paymob.notification_url') ?: route('payments.paymob.callback');
        $redirect = config('paymob.redirection_url') ?: route('payments.paymob.return');
        $integrationId = (int) config('paymob.card_integration_id');
        if ($integrationId < 1 || ! config('paymob.public_key')) {
            throw new PaymentCreationFailed('Payment is temporarily unavailable.');
        }

        $amount = Money::toMinorUnits((string) $order->customer_price);
        $data = $this->client->createIntention([
            'amount' => $amount,
            'currency' => strtoupper($order->currency),
            'payment_methods' => [$integrationId],
            'items' => [['name' => 'Domain registration: '.$order->domain, 'amount' => $amount, 'description' => 'Domain registration', 'quantity' => 1]],
            'billing_data' => array_filter([
                'first_name' => $firstName, 'last_name' => $lastName, 'email' => $contact['email'] ?? null,
                'phone_number' => $contact['voice'] ?? null, 'country' => $contact['country'] ?? null,
                'city' => $contact['city'] ?? null, 'street' => $contact['street'] ?? null,
                'state' => $contact['province'] ?? null, 'postal_code' => $contact['postal_code'] ?? null,
            ], static fn ($value) => $value !== null && $value !== ''),
            'special_reference' => $reference,
            'expiration' => (int) config('paymob.payment_expiration', 3600),
            'notification_url' => $notification,
            'redirection_url' => $redirect,
        ]);

        return new PaymentSession(rtrim((string) config('paymob.base_url'), '/').'/unifiedcheckout/?publicKey='.urlencode((string) config('paymob.public_key')).'&clientSecret='.urlencode($data->clientSecret), $data->id, $data->orderId, $data->clientSecret, $reference, $data->status);
    }
}
