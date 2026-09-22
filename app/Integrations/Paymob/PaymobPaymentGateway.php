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
        $billing = $order->billing_data;
        foreach (['first_name', 'last_name', 'email', 'phone_number', 'country', 'city', 'street', 'state', 'postal_code'] as $field) {
            if (! is_array($billing) || ! isset($billing[$field]) || trim((string) $billing[$field]) === '') {
                throw new PaymentCreationFailed('Payment billing information is missing.');
            }
        }
        $reference = 'order-'.$order->id;
        $notification = config('paymob.notification_url') ?: route('payments.paymob.callback');
        $redirect = config('paymob.redirection_url') ?: route('payments.paymob.return');
        $integrationId = (int) config('paymob.card_integration_id');
        if ($integrationId < 1 || ! config('paymob.public_key')) {
            throw new PaymentCreationFailed('Payment is temporarily unavailable.');
        }

        $amount = Money::toMinorUnits((string) $order->customer_price);
        [$itemName, $description] = match ($order->type) {
            'domain_registration' => ['Domain registration: '.$order->domain, 'Domain registration'],
            'domain_renewal' => ['Domain renewal: '.$order->domain, 'Domain renewal'],
            default => throw new PaymentCreationFailed('This order type cannot be paid.'),
        };
        $data = $this->client->createIntention([
            'amount' => $amount,
            'currency' => strtoupper($order->currency),
            'payment_methods' => [$integrationId],
            'items' => [['name' => $itemName, 'amount' => $amount, 'description' => $description, 'quantity' => 1]],
            'billing_data' => $billing,
            'special_reference' => $reference,
            'expiration' => (int) config('paymob.payment_expiration', 3600),
            'notification_url' => $notification,
            'redirection_url' => $redirect,
        ]);

        return new PaymentSession(rtrim((string) config('paymob.base_url'), '/').'/unifiedcheckout/?publicKey='.urlencode((string) config('paymob.public_key')).'&clientSecret='.urlencode($data->clientSecret), $data->id, $data->orderId, $data->clientSecret, $reference, $data->status);
    }
}
