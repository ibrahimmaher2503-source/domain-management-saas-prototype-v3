<?php

namespace App\Jobs;

use App\Domain\Ssl\Contracts\SslProvider;
use App\Integrations\OnlineNic\Exceptions\OnlineNicException;
use App\Models\SslCertificate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class ReconcileSslCertificate implements ShouldQueue
{
    use Dispatchable,InteractsWithQueue,Queueable,SerializesModels;

    public int $tries = 1;

    public int $timeout = 75;

    public function __construct(public readonly int $certificateId, public readonly int $attempt = 1) {}

    public function handle(SslProvider $provider): void
    {
        $c = SslCertificate::with('order.registrarOperations')->find($this->certificateId);
        $operation = $c?->order?->registrarOperations->where('status', 'ambiguous')->whereIn('operation', ['cancel_certificate', 'change_approver_email', 'reissue_certificate'])->sortByDesc('id')->first();
        if (! $c || ! $c->provider_order_id || (in_array($c->status, ['issued', 'failed', 'cancelled'], true) && ! $operation)) {
            return;
        }try {
            $r = $provider->getCertificateOrder($c->provider_order_id);
        } catch (OnlineNicException) {
            $this->again($c);

            return;
        }$c->update($r + ['provider_synced_at' => now()]);
        $resolved = match ($operation?->operation) {
            'cancel_certificate' => $r['status'] === 'cancelled',
            'change_approver_email' => isset($r['approver_email']) && $r['approver_email'] === ($operation->safe_request_metadata['new_approver_email'] ?? null),
            'reissue_certificate' => in_array($r['status'], ['processing', 'pending_validation'], true),
            default => false,
        };
        if ($resolved) {
            $operation->update(['status' => 'completed', 'provider_code' => '1000', 'completed_at' => now()]);
        }
        $c->order?->update(['status' => $r['status'] === 'issued' ? 'completed' : ($r['status'] === 'failed' ? 'failed' : 'provisioning')]);
        if ($operation && ! $resolved) {
            $this->again($c);
        } elseif (! in_array($r['status'], ['issued', 'failed', 'cancelled'], true)) {
            $this->again($c);
        }
    }

    private function again(SslCertificate $c): void
    {
        if ($this->attempt < 3) {
            self::dispatch($c->id, $this->attempt + 1)->delay(now()->addMinutes(5));
        } else {
            $c->update(['status' => 'action_required']);
        }
    }
}
