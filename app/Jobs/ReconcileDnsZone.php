<?php

namespace App\Jobs;

use App\Domain\Dns\Contracts\DnsProvider;
use App\Domain\Dns\Exceptions\DnsProviderException;
use App\Domain\Dns\Services\ManageDnsRecords;
use App\Models\DnsZone;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class ReconcileDnsZone implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 30;

    public function __construct(public readonly int $zoneId) {}

    public function handle(ManageDnsRecords $records, DnsProvider $provider): void
    {
        $zone = DnsZone::with('domain')->find($this->zoneId);
        if (! $zone || ! $zone->operations()->whereIn('status', ['pending', 'ambiguous'])->exists()) {
            return;
        }
        try {
            if (! $zone->provider_zone_id) {
                $remote = $provider->findZone($zone->domain->name);
                if ($remote) {
                    $zone->update(['provider_zone_id' => $remote['id'], 'provider_status' => $remote['status'], 'assigned_nameservers' => $remote['nameservers'], 'provider_synced_at' => now()]);
                    $zone->operations()->where('operation', 'connect')->whereIn('status', ['pending', 'ambiguous'])->update(['status' => 'completed']);
                }

                return;
            }
            $records->reconcile($zone);
        } catch (DnsProviderException) {
            // The sweep is read-only; a later scheduled pass can try again.
        }
    }
}
