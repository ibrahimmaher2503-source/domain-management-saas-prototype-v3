<?php

namespace App\Domain\Dns\Services;

use App\Domain\Dns\Contracts\DnsProvider;
use App\Domain\Dns\Exceptions\AmbiguousDnsWrite;
use App\Domain\Dns\Exceptions\DnsProviderException;
use App\Domain\Domains\Services\ChangeDomainNameservers;
use App\Domain\Registrar\DTOs\UpdateNameserversData;
use App\Models\DnsZone;
use App\Models\Domain;
use Illuminate\Support\Facades\DB;

final class ConnectDomainDns
{
    public function __construct(private readonly DnsProvider $provider, private readonly ChangeDomainNameservers $nameservers) {}

    public function connect(Domain $domain): DnsZone
    {
        abort_unless(auth()->id() === $domain->user_id, 404);
        abort_unless($domain->status === 'active' && $domain->provider === 'onlinenic' && $domain->tld === 'com', 422);
        $zone = DB::transaction(function () use ($domain): DnsZone {
            Domain::query()->whereKey($domain->id)->lockForUpdate()->firstOrFail();

            return DnsZone::firstOrCreate(['domain_id' => $domain->id], ['provider' => 'cloudflare', 'status' => 'pending']);
        });
        if ($zone->provider_zone_id) {
            return $zone->status === 'active' ? $zone : $this->delegate($domain, $zone);
        }
        // A prior request may have reached Cloudflare. Find it before considering another create.
        $claimed = false;
        try {
            $remote = $this->provider->findZone($domain->name);
            if ($remote === null && ! $zone->creation_attempted) {
                $claimed = DnsZone::query()->whereKey($zone->id)->where('creation_attempted', false)->update(['creation_attempted' => true]);
                if (! $claimed) {
                    return $zone;
                }
                $remote = $this->provider->createZone($domain->name);
            }
            if ($remote === null) {
                return $zone;
            }
            $zone->update(['provider_zone_id' => $remote['id'], 'provider_status' => $remote['status'], 'assigned_nameservers' => $remote['nameservers'], 'provider_synced_at' => now()]);
            if (! $zone->operations()->where('operation', 'connect')->exists()) {
                $zone->operations()->create(['operation' => 'connect', 'status' => 'completed']);
            }
        } catch (AmbiguousDnsWrite) {
            return $zone->fresh();
        } catch (DnsProviderException $exception) {
            // A clear rejection did not create a zone, so a later attempt is safe.
            if ($claimed) {
                $zone->update(['creation_attempted' => false]);
            }
            throw $exception;
        }

        return $this->delegate($domain, $zone);
    }

    public function delegate(Domain $domain, DnsZone $zone): DnsZone
    {
        if (! $zone->provider_zone_id || ! $zone->assigned_nameservers) {
            return $zone;
        }
        // Existing registrar service guards pending/ambiguous writes and confirms via InfoDomain.
        $this->nameservers->change($domain, new UpdateNameserversData($domain->name, $zone->assigned_nameservers));

        return $zone->fresh();
    }
}
