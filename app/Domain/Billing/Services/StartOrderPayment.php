<?php

namespace App\Domain\Billing\Services;

use App\Domain\Billing\Contracts\PaymentGateway;
use App\Domain\Billing\DTOs\PaymentSession;
use App\Domain\Billing\Money;
use App\Integrations\Paymob\Exceptions\PaymobException;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class StartOrderPayment
{
    public function __construct(private readonly PaymentGateway $gateway) {}

    public function start(User $user, Order $order): PaymentSession
    {
        [$payment, $create] = DB::transaction(function () use ($user, $order): array {
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->user_id === $user->id, 404);
            if ($locked->status !== 'awaiting_payment') {
                throw new PaymobException('This order is not awaiting payment.');
            }
            if ($locked->currency === '' || Money::toMinorUnits((string) $locked->customer_price) < 1) {
                throw new PaymobException('This order cannot be paid.');
            }

            $existing = $locked->payments()->where('status', 'pending')->latest()->first();
            if ($existing) {
                return [$existing, false];
            }

            $retry = $locked->payments()->where('status', 'failed')->latest()->first();
            if ($retry) {
                $retry->update(['status' => 'pending', 'failed_at' => null, 'provider_intention_id' => null, 'provider_order_id' => null, 'provider_transaction_id' => null, 'provider_status' => null, 'checkout_reference' => null, 'provider_metadata' => null]);

                return [$retry->fresh(), true];
            }

            return [$locked->payments()->create([
                'user_id' => $user->id, 'provider' => 'paymob', 'status' => 'pending', 'amount' => $locked->customer_price,
                'currency' => strtoupper($locked->currency), 'provider_reference' => 'order-'.$locked->id,
            ]), true];
        });

        if ($payment->checkout_reference) {
            return new PaymentSession($payment->checkout_reference, (string) $payment->provider_intention_id, (int) $payment->provider_order_id, (string) data_get($payment->provider_metadata, 'client_secret'), $payment->provider_reference, (string) $payment->provider_status);
        }
        if (! $create) {
            throw new PaymobException('Payment is already being prepared.');
        }

        try {
            $session = $this->gateway->createPayment($order->fresh());
        } catch (\Throwable $exception) {
            $payment->update(['status' => 'failed', 'failed_at' => now()]);
            throw $exception;
        }

        $payment->update([
            'provider_intention_id' => $session->providerIntentionId, 'provider_order_id' => $session->providerOrderId,
            'provider_status' => $session->providerStatus, 'checkout_reference' => $session->checkoutUrl,
            'provider_metadata' => ['client_secret' => $session->clientSecret],
        ]);

        return $session;
    }
}
