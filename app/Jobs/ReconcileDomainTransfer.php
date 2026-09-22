<?php

namespace App\Jobs;

use App\Domain\Registrar\Contracts\RegistrarGateway;
use App\Integrations\OnlineNic\Exceptions\OnlineNicException;
use App\Models\Domain;
use App\Models\Transfer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

final class ReconcileDomainTransfer implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public readonly int $transferId, public readonly int $attempt = 1) {}

    public function handle(RegistrarGateway $registrar): void
    {
        $transfer = Transfer::with('order')->find($this->transferId);
        if (! $transfer || in_array($transfer->status, ['completed', 'failed', 'cancelled'], true)) {
            return;
        }
        try {
            $result = $registrar->getRegistrarTransferStatus($transfer->domain);
        } catch (OnlineNicException) {
            $this->again($transfer);

            return;
        }
        $transfer->update(['provider_status' => $result->providerStatus, 'provider_synced_at' => now()]);
        if ($result->status === 'completed') {
            $this->complete($transfer, $registrar);

            return;
        }
        if (in_array($result->status, ['failed', 'cancelled'], true)) {
            $transfer->update(['status' => $result->status, 'cancelled_at' => $result->status === 'cancelled' ? now() : null]);
            $transfer->order?->update(['status' => $result->status === 'failed' ? 'failed' : 'completed', 'provisioning_failure_reason' => $result->status === 'failed' ? 'Payment was successful, but the registrar transfer failed.' : null]);

            return;
        }
        $transfer->update(['status' => $result->status]);
        $this->again($transfer);
    }

    private function complete(Transfer $transfer, RegistrarGateway $registrar): void
    {
        $existing = Domain::where('name', $transfer->domain)->first();
        if ($existing && $existing->user_id !== $transfer->user_id) {
            $transfer->update(['status' => 'action_required']);

            return;
        }
        try {
            $info = $registrar->getDomainInfo($transfer->domain);
        } catch (OnlineNicException) {
            $transfer->update(['status' => 'action_required']);

            return;
        }
        DB::transaction(function () use ($transfer, $existing, $info): void {
            $domain = $existing ?? Domain::create(['user_id' => $transfer->user_id, 'order_id' => $transfer->order_id, 'name' => $transfer->domain, 'tld' => $transfer->tld, 'provider' => $transfer->provider, 'status' => 'active', 'registered_at' => $info->registeredAt, 'expires_at' => $info->expiresAt, 'nameservers' => $info->nameservers, 'transfer_locked' => $info->transferLocked, 'provider_status' => $info->providerStatus, 'provider_synced_at' => now()]);
            if ($existing) {
                $domain->update(['status' => 'active', 'registered_at' => $info->registeredAt, 'expires_at' => $info->expiresAt, 'nameservers' => $info->nameservers, 'transfer_locked' => $info->transferLocked, 'provider_status' => $info->providerStatus, 'provider_synced_at' => now()]);
            }
            $transfer->update(['domain_id' => $domain->id, 'status' => 'completed', 'completed_at' => now(), 'provider_synced_at' => now()]);
            $transfer->order?->update(['domain_id' => $domain->id, 'status' => 'completed', 'provisioning_failure_reason' => null]);
            $transfer->order?->registrarOperations()->where('operation', 'request_registrar_transfer')->update(['status' => 'completed', 'completed_at' => now()]);
        });
    }

    private function again(Transfer $transfer): void
    {
        if ($this->attempt < 3) {
            self::dispatch($transfer->id, $this->attempt + 1)->onConnection('database')->delay(now()->addMinutes([2 => 5, 3 => 15][$this->attempt + 1]));
        } else {
            $transfer->update(['status' => in_array($transfer->status, ['ambiguous', 'action_required'], true) ? $transfer->status : 'action_required']);
        }
    }
}
