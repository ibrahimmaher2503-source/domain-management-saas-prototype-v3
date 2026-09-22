<?php

namespace App\Http\Controllers;

use App\Domain\Dns\Exceptions\DnsProviderException;
use App\Domain\Dns\Services\ConnectDomainDns;
use App\Domain\Dns\Services\DnsRecordData;
use App\Domain\Dns\Services\ManageDnsRecords;
use App\Domain\Dns\Services\SyncDnsZone;
use App\Domain\Domains\Services\SyncDomainFromRegistrar;
use App\Integrations\OnlineNic\Exceptions\OnlineNicException;
use App\Models\Domain;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class DomainDnsController extends Controller
{
    public function connect(Request $request, Domain $domain, ConnectDomainDns $connect): RedirectResponse
    {
        $this->owned($request, $domain);
        try {
            $zone = $connect->connect($domain);
        } catch (DnsProviderException) {
            return back()->with('domain_error', 'DNS connection could not be completed. Please refresh its status before trying again.');
        }

        return to_route('domains.show', ['domain' => $domain, 'tab' => 'dns'])->with($zone->provider_zone_id ? 'domain_notice' : 'domain_error', $zone->provider_zone_id ? 'DNS zone created. Delegation and activation may still need confirmation.' : 'DNS setup needs confirmation. Refresh status; do not create another zone.');
    }

    public function sync(Request $request, Domain $domain, SyncDnsZone $sync, SyncDomainFromRegistrar $registrar, ManageDnsRecords $records): RedirectResponse
    {
        $this->owned($request, $domain);
        $zone = $domain->dnsZone;
        abort_unless($zone, 404);
        try {
            $sync->sync($zone);
            try {
                $registrar->sync($domain);
            } catch (OnlineNicException) { /* Cloudflare status can still be refreshed */
            }
            if ($zone->fresh()->status === 'active') {
                $records->reconcile($zone);
            }
        } catch (DnsProviderException) {
            return back()->with('domain_error', 'DNS status could not be refreshed. Please try later.');
        }

        return to_route('domains.show', ['domain' => $domain, 'tab' => 'dns'])->with('domain_notice', 'DNS status refreshed.');
    }

    public function store(Request $request, Domain $domain, ManageDnsRecords $records): RedirectResponse
    {
        $this->owned($request, $domain);
        $data = DnsRecordData::fromInput($request->all(), $domain->name);

        return $this->write($domain, $records, 'create', null, $data);
    }

    public function update(Request $request, Domain $domain, string $record, ManageDnsRecords $records): RedirectResponse
    {
        $this->owned($request, $domain);
        $data = DnsRecordData::fromInput($request->all(), $domain->name);

        return $this->write($domain, $records, 'update', $record, $data);
    }

    public function destroy(Request $request, Domain $domain, string $record, ManageDnsRecords $records): RedirectResponse
    {
        $this->owned($request, $domain);
        $request->validate(['confirm' => ['accepted']]);

        return $this->write($domain, $records, 'delete', $record, null);
    }

    private function write(Domain $domain, ManageDnsRecords $records, string $operation, ?string $recordId, ?array $data): RedirectResponse
    {
        abort_unless($domain->dnsZone, 404);
        try {
            $result = $records->write($domain->dnsZone, $operation, $recordId, $data);
        } catch (DnsProviderException $exception) {
            return back()->with('domain_error', $exception->getMessage());
        }

        return to_route('domains.show', ['domain' => $domain, 'tab' => 'dns'])->with($result === 'completed' ? 'domain_notice' : 'domain_error', $result === 'completed' ? 'DNS record change completed.' : 'DNS change needs confirmation. Refresh records before another change.');
    }

    private function owned(Request $request, Domain $domain): void
    {
        abort_unless($domain->user_id === $request->user()->id, 404);
    }
}
