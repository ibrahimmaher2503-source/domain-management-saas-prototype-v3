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

    public function __construct(public readonly int $certificateId, public readonly int $attempt = 1) {}

    public function handle(SslProvider $provider): void
    {
        $c = SslCertificate::with('order')->find($this->certificateId);
        if (! $c || ! $c->provider_order_id || in_array($c->status, ['issued', 'failed', 'cancelled'], true)) {
            return;
        }try {
            $r = $provider->getCertificateOrder($c->provider_order_id);
        } catch (OnlineNicException) {
            $this->again($c);

            return;
        }$c->update($r + ['provider_synced_at' => now()]);
        $c->order?->update(['status' => $r['status'] === 'issued' ? 'completed' : ($r['status'] === 'failed' ? 'failed' : 'provisioning')]);
        if (! in_array($r['status'], ['issued', 'failed', 'cancelled'], true)) {
            $this->again($c);
        }
    }

    private function again(SslCertificate $c): void
    {
        if ($this->attempt < 3) {
            self::dispatch($c->id, $this->attempt + 1)->onConnection('database')->delay(now()->addMinutes(5));
        } else {
            $c->update(['status' => 'action_required']);
        }
    }
}
