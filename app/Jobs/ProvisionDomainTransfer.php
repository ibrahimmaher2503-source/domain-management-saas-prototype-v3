<?php

namespace App\Jobs;

use App\Domain\Registrar\Contracts\RegistrarGateway;
use App\Domain\Registrar\DTOs\RequestTransferData;
use App\Integrations\OnlineNic\Exceptions\OnlineNicException;
use App\Integrations\OnlineNic\Exceptions\ProviderAmbiguousResponse;
use App\Integrations\OnlineNic\OnlineNicTransactionIdGenerator;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

final class ProvisionDomainTransfer implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 150;

    public function __construct(public readonly int $orderId) {}

    public function handle(RegistrarGateway $registrar, OnlineNicTransactionIdGenerator $transactions): void
    {
        $order = DB::transaction(function (): ?Order {
            $order = Order::with(['payments'])->whereKey($this->orderId)->lockForUpdate()->first();
            if (! $order) {
                return null;
            }
            $transfer = $order->user->transfers()->where('order_id', $order->id)->first();
            if (! $transfer || $order->type !== 'domain_transfer' || $order->status !== 'paid' || ! $order->payments()->where('status', 'paid')->exists() || $transfer->status !== 'awaiting_payment') {
                return null;
            }
            $order->update(['status' => 'provisioning']);
            $transfer->update(['status' => 'paid']);

            return $order;
        });
        if (! $order) {
            return;
        }
        $transfer = $order->user->transfers()->where('order_id', $order->id)->firstOrFail();
        $operation = $order->registrarOperations()->create(['user_id' => $order->user_id, 'provider' => 'onlinenic', 'operation' => 'request_registrar_transfer', 'cltrid' => $transactions->generate(), 'status' => 'pending', 'safe_request_metadata' => ['domain' => $order->domain, 'mailway' => 'provider'], 'started_at' => now()]);
        try {
            $result = $registrar->requestRegistrarTransfer(new RequestTransferData($order->domain), $operation->cltrid);
        } catch (ProviderAmbiguousResponse) {
            $operation->update(['status' => 'ambiguous', 'provider_message' => 'Provider response was ambiguous.']);
            $transfer->update(['status' => 'ambiguous', 'requested_at' => now()]);
            ReconcileDomainTransfer::dispatch($transfer->id)->delay(now()->addMinute());

            return;
        } catch (OnlineNicException $exception) {
            $operation->update(['status' => 'failed', 'provider_code' => $exception->providerCode, 'provider_message' => $exception->providerMessage, 'completed_at' => now()]);
            $transfer->update(['status' => 'failed']);
            $order->update(['status' => 'failed', 'provisioning_failure_reason' => 'Payment was successful, but the transfer request was rejected.']);

            return;
        }
        $operation->update(['status' => $result->status === 'completed' ? 'completed' : 'pending', 'svtrid' => $result->svtrid, 'provider_code' => $result->code, 'provider_metadata' => ['status' => $result->providerStatus], 'completed_at' => $result->status === 'completed' ? now() : null]);
        $transfer->update(['status' => $result->status, 'provider_status' => $result->providerStatus, 'requested_at' => now(), 'provider_synced_at' => now()]);
        ReconcileDomainTransfer::dispatch($transfer->id)->delay(now()->addMinute());
    }
}
