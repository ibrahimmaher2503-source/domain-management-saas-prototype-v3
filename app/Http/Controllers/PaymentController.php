<?php

namespace App\Http\Controllers;

use App\Domain\Billing\Money;
use App\Domain\Billing\Services\StartOrderPayment;
use App\Integrations\Paymob\Exceptions\PaymobException;
use App\Integrations\Paymob\PaymobHmacVerifier;
use App\Jobs\ProvisionDomainRegistration;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

final class PaymentController extends Controller
{
    public function pay(Request $request, Order $order, StartOrderPayment $start): RedirectResponse
    {
        abort_unless($order->user_id === $request->user()->id, 404);
        try {
            return redirect()->away($start->start($request->user(), $order)->checkoutUrl);
        } catch (PaymobException $exception) {
            return back()->withErrors(['payment' => 'Payment is temporarily unavailable. Please try again.']);
        }
    }

    public function callback(Request $request, PaymobHmacVerifier $verifier): JsonResponse
    {
        $payload = $request->all();
        abort_unless($verifier->verify($payload, $request->query('hmac') ?: $request->input('hmac') ?: data_get($payload, 'obj.hmac')), 403);
        if (($payload['type'] ?? '') !== 'TRANSACTION' || ! is_array($payload['obj'] ?? null)) {
            return response()->json(['received' => true]);
        }
        $transaction = $payload['obj'];
        $providerOrderId = (int) data_get($transaction, 'order.id');
        $transactionId = (string) ($transaction['id'] ?? '');
        if ($providerOrderId < 1 || $transactionId === '') {
            return response()->json(['received' => true]);
        }

        DB::transaction(function () use ($providerOrderId, $transactionId, $transaction): void {
            $payment = Payment::query()->where('provider', 'paymob')->where('provider_order_id', $providerOrderId)->lockForUpdate()->first();
            if (! $payment || $payment->status === 'paid') {
                return;
            }
            $amountMatches = (int) ($transaction['amount_cents'] ?? -1) === Money::toMinorUnits((string) $payment->amount);
            $currencyMatches = strtoupper((string) ($transaction['currency'] ?? '')) === strtoupper($payment->currency);
            if (($transaction['success'] ?? false) === true && (! $amountMatches || ! $currencyMatches)) {
                return;
            }
            $payment->update(['provider_transaction_id' => $transactionId, 'provider_status' => ($transaction['success'] ?? false) ? 'success' : 'failed', 'provider_metadata' => ['callback' => ['success' => (bool) ($transaction['success'] ?? false), 'pending' => (bool) ($transaction['pending'] ?? false)]]]);
            if (($transaction['success'] ?? false) === true && ($transaction['pending'] ?? true) === false && $amountMatches && $currencyMatches) {
                $payment->update(['status' => 'paid', 'paid_at' => now()]);
                if ($payment->order()->where('status', 'awaiting_payment')->update(['status' => 'paid']) === 1) {
                    ProvisionDomainRegistration::dispatch($payment->order_id)->onConnection('database')->afterCommit();
                }
            } elseif (($transaction['success'] ?? false) === false && ($transaction['pending'] ?? true) === false) {
                $payment->update(['status' => 'failed', 'failed_at' => now()]);
            }
        });

        return response()->json(['received' => true]);
    }

    public function return(Request $request): Response
    {
        $payment = Payment::query()->where('provider', 'paymob')->where('provider_order_id', (int) $request->query('order'))->latest()->first();
        $state = $payment?->status === 'paid' ? 'paid' : ($payment?->status === 'failed' ? 'failed' : 'processing');

        return Inertia::render('Payments/Return', ['state' => $state]);
    }
}
