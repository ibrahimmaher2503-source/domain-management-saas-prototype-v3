<?php

namespace App\Jobs;

use App\Domain\Ssl\Contracts\SslProvider;
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

final class ProvisionSslCertificate implements ShouldQueue
{
    use Dispatchable,InteractsWithQueue,Queueable,SerializesModels;

    public int $tries = 1;

    public function __construct(public readonly int $orderId) {}

    public function handle(SslProvider $provider, OnlineNicTransactionIdGenerator $tx): void
    {
        $order = DB::transaction(function () {
            $o = Order::with('sslCertificate')->lockForUpdate()->find($this->orderId);
            if (! $o || $o->type !== 'ssl_certificate' || $o->status !== 'paid' || ! $o->payments()->where('status', 'paid')->exists() || ! $o->sslCertificate || $o->sslCertificate->status !== 'awaiting_payment' || ! $o->ssl_data) {
                return null;
            } $o->update(['status' => 'provisioning']);
            $o->sslCertificate->update(['status' => 'ordering']);

            return $o;
        });
        if (! $order) {
            return;
        } $c = $order->sslCertificate;
        $op = $order->registrarOperations()->create(['user_id' => $order->user_id, 'provider' => 'onlinenic', 'operation' => 'ssl_order', 'cltrid' => $tx->generate(), 'status' => 'pending', 'safe_request_metadata' => ['domain' => $order->domain, 'product_key' => $c->product_key, 'validity' => $order->registration_period], 'started_at' => now()]);
        try {
            $d = $order->ssl_data;
            $r = $provider->orderCertificate(['product' => $c->provider_product, 'validity' => (int) $d['validity'], 'server' => config('ssl.web_server_type'), 'first_name' => $d['first_name'], 'last_name' => $d['last_name'], 'phone' => $d['phone'], 'email' => $d['email'], 'approver_email' => $d['approver_email'], 'csr' => $d['csr']], $op->cltrid);
            $c->update(['provider_order_id' => $r['order_id'], 'status' => 'pending_validation', 'provider_synced_at' => now()]);
            if ($r['price'] !== null) {
                $order->update(['provider_cost' => $r['price']]);
            }
            $op->update(['status' => 'completed', 'provider_code' => '1000', 'provider_metadata' => ['provider_order_id' => $r['order_id']], 'completed_at' => now()]);
        } catch (ProviderAmbiguousResponse) {
            $op->update(['status' => 'ambiguous', 'provider_message' => 'Provider response was ambiguous.']);
            $c->update(['status' => 'ambiguous']);
        } catch (OnlineNicException $e) {
            $op->update(['status' => 'failed', 'provider_code' => $e->providerCode, 'provider_message' => $e->providerMessage, 'completed_at' => now()]);
            $c->update(['status' => 'failed']);
            $order->update(['status' => 'failed', 'provisioning_failure_reason' => 'Payment was successful, but the SSL order was rejected.']);
        }
    }
}
