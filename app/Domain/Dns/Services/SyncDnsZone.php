<?php

namespace App\Domain\Dns\Services;

use App\Domain\Dns\Contracts\DnsProvider;
use App\Domain\Dns\Exceptions\DnsProviderException;
use App\Models\DnsZone;

final class SyncDnsZone
{
    public function __construct(private readonly DnsProvider $provider) {}

    public function sync(DnsZone $zone): DnsZone
    {
        $remote = $zone->provider_zone_id
            ? $this->provider->getZone($zone->provider_zone_id)
            : $this->provider->findZone($zone->domain->name);
        if ($remote === null) {
            return $zone;
        }
        if ($zone->provider_zone_id && $remote['id'] !== $zone->provider_zone_id) {
            throw new DnsProviderException('DNS zone identity could not be confirmed.');
        }
        $status = match ($remote['status']) {
            'active' => 'active',
            'pending', 'initializing' => 'pending',
            default => 'error',
        };
        $wasActive = $zone->status === 'active';
        $zone->update(['provider_zone_id' => $remote['id'], 'status' => $status, 'provider_status' => $remote['status'], 'assigned_nameservers' => $remote['nameservers'], 'provider_synced_at' => now()]);
        if (! $wasActive && $status === 'active') {
            $zone->operations()->create(['operation' => 'zone_activated', 'status' => 'completed']);
        }

        return $zone->fresh();
    }
}
