<?php

namespace App\Http\Controllers;

use App\Domain\Dns\Exceptions\DnsProviderException;
use App\Domain\Dns\Services\ManageDnsRecords;
use App\Domain\Domains\Services\ChangeDomainNameservers;
use App\Domain\Domains\Services\SyncDomainFromRegistrar;
use App\Domain\Registrar\DTOs\UpdateNameserversData;
use App\Integrations\OnlineNic\Exceptions\OnlineNicException;
use App\Models\Domain;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

final class DomainController extends Controller
{
    public function index(Request $request): Response
    {
        $domains = $request->user()->domains()->latest()->get()->map(fn (Domain $domain) => $this->summary($domain))->values();

        return Inertia::render('Domains/Index', ['domains' => $domains]);
    }

    public function show(Request $request, Domain $domain, ManageDnsRecords $records, SyncDomainFromRegistrar $registrarSync): Response
    {
        abort_unless($domain->user_id === $request->user()->id, 404);

        $activity = $domain->registrarOperations()->whereIn('operation', ['domain_registration', 'domain_sync', 'update_nameservers', 'set_transfer_lock', 'get_auth_code'])->latest()->limit(20)->get()->map(static fn ($operation): array => [
            'id' => $operation->id,
            'label' => match ($operation->operation) {
                'domain_registration' => 'Domain registered',
                'domain_sync' => 'Domain synced',
                'get_auth_code' => 'Transfer code requested',
                'set_transfer_lock' => match ($operation->status) {
                    'completed' => ($operation->safe_request_metadata['locked'] ?? false) ? 'Transfer lock enabled' : 'Transfer lock disabled',
                    'failed' => 'Transfer lock change failed',
                    default => 'Transfer lock awaiting confirmation',
                },
                default => match ($operation->status) {
                    'completed' => 'Nameservers updated',
                    'failed' => 'Nameserver change failed',
                    default => 'Nameserver change awaiting confirmation',
                },
            },
            'status' => $operation->status,
            'at' => $operation->completed_at?->toIso8601String() ?? $operation->started_at?->toIso8601String(),
        ]);

        $zone = $domain->dnsZone;
        $dnsRecords = [];
        $dnsError = null;
        if ($request->query('tab') === 'dns' && $zone?->status === 'active') {
            try {
                $dnsRecords = $records->records($zone);
            } catch (DnsProviderException) {
                $dnsError = 'DNS records could not be loaded. Please try again later.';
            }
        }
        $dnsActivity = $zone?->operations()->where('status', 'completed')->latest()->limit(20)->get()->map(static fn ($item): array => [
            'id' => 'dns-'.$item->id,
            'label' => $item->operation === 'zone_activated' ? 'DNS zone activated' : ($item->operation === 'connect' ? 'DNS connected' : ($item->record_type.' record '.match ($item->operation) {
                'create' => 'added', 'update' => 'updated', default => 'deleted'
            })),
            'status' => $item->status,
            'at' => $item->updated_at?->toIso8601String(),
        ])->all() ?? [];

        return Inertia::render('Domains/Show', ['domain' => $this->summary($domain), 'securityPending' => $domain->registrarOperations()->where('operation', 'set_transfer_lock')->whereIn('status', ['pending', 'ambiguous'])->exists(), 'dnsZone' => $zone ? ['status' => $zone->status, 'assigned_nameservers' => $zone->assigned_nameservers, 'provider_synced_at' => $zone->provider_synced_at?->toIso8601String(), 'delegated' => $registrarSync->sameNameservers($domain->nameservers ?? [], $zone->assigned_nameservers ?? []), 'ambiguous' => $zone->operations()->whereIn('status', ['pending', 'ambiguous'])->exists()] : null, 'dnsRecords' => $dnsRecords, 'dnsError' => $dnsError, 'initialTab' => $request->query('tab') === 'dns' ? 'DNS' : 'Overview', 'activity' => collect(array_merge($activity->all(), $dnsActivity))->sortByDesc('at')->take(20)->values()->all(), 'notice' => session('domain_notice'), 'error' => session('domain_error')]);
    }

    public function sync(Request $request, Domain $domain, SyncDomainFromRegistrar $sync): RedirectResponse
    {
        abort_unless($domain->user_id === $request->user()->id, 404);
        try {
            $sync->sync($domain);
        } catch (OnlineNicException) {
            return back()->with('domain_error', 'Domain information could not be refreshed. Please try again later.');
        }

        return back()->with('domain_notice', 'Domain information refreshed.');
    }

    public function updateNameservers(Request $request, Domain $domain, ChangeDomainNameservers $change): RedirectResponse
    {
        abort_unless($domain->user_id === $request->user()->id, 404);
        $validated = $request->validate(['nameservers' => ['required', 'array', 'min:2', 'max:6'], 'nameservers.*' => ['required', 'string', 'max:120']]);
        try {
            $result = $change->change($domain, new UpdateNameserversData($domain->name, $validated['nameservers']));
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['nameservers' => $exception->getMessage()]);
        }

        return match ($result) {
            'completed' => back()->with('domain_notice', 'Nameserver change accepted. Current registrar data has been refreshed where available.'),
            'unchanged' => back()->with('domain_notice', 'Nameservers are unchanged.'),
            'ambiguous', 'blocked' => back()->with('domain_error', 'The nameserver change is being confirmed. Please refresh later; do not submit it again.'),
            default => back()->with('domain_error', 'Nameservers could not be changed. Please try again later.'),
        };
    }

    private function summary(Domain $domain): array
    {
        return ['id' => $domain->id, 'name' => $domain->name, 'status' => $domain->status, 'registered_at' => $domain->registered_at?->toDateString(), 'expires_at' => $domain->expires_at?->toDateString(), 'nameservers' => $domain->nameservers, 'transfer_locked' => $domain->transfer_locked, 'provider_synced_at' => $domain->provider_synced_at?->toIso8601String()];
    }
}
