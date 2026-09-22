<?php

namespace App\Domain\Domains\Services;

use App\Domain\Registrar\Contracts\RegistrarGateway;
use App\Domain\Registrar\DTOs\UpdateNameserversData;
use App\Integrations\OnlineNic\Exceptions\OnlineNicException;
use App\Integrations\OnlineNic\Exceptions\ProviderAmbiguousResponse;
use App\Integrations\OnlineNic\OnlineNicTransactionIdGenerator;
use App\Models\Domain;
use App\Models\RegistrarOperation;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class ChangeDomainNameservers
{
    public function __construct(private readonly RegistrarGateway $registrar, private readonly SyncDomainFromRegistrar $sync, private readonly OnlineNicTransactionIdGenerator $transactions) {}

    public function change(Domain $domain, UpdateNameserversData $data): string
    {
        if ($domain->provider !== 'onlinenic' || $domain->tld !== 'com' || $domain->status !== 'active' || strcasecmp($domain->name, $data->domain) !== 0) {
            throw new InvalidArgumentException('Nameserver changes are unavailable for this domain.');
        }

        $operation = DB::transaction(function () use ($domain, $data): RegistrarOperation|string {
            $locked = Domain::query()->whereKey($domain->id)->lockForUpdate()->firstOrFail();
            if ($locked->registrarOperations()->where('operation', 'update_nameservers')->whereIn('status', ['pending', 'ambiguous'])->exists()) {
                return 'blocked';
            }
            if ($this->sync->sameNameservers($locked->nameservers, $data->nameservers)) {
                return 'unchanged';
            }
            $lastWrite = $locked->registrarOperations()->where('operation', 'update_nameservers')->latest('id')->first();
            if ($lastWrite?->status === 'completed' && $this->sync->sameNameservers($lastWrite->safe_request_metadata['nameservers'] ?? [], $data->nameservers)) {
                return 'blocked';
            }

            return $locked->registrarOperations()->create([
                'user_id' => $locked->user_id, 'order_id' => $locked->order_id,
                'provider' => $locked->provider, 'operation' => 'update_nameservers',
                'cltrid' => $this->transactions->generate(), 'status' => 'pending',
                'safe_request_metadata' => ['nameservers' => $data->nameservers],
                'started_at' => now(),
            ]);
        });
        if (is_string($operation)) {
            return $operation;
        }

        try {
            $result = $this->registrar->updateNameservers($data, $operation->cltrid);
        } catch (ProviderAmbiguousResponse) {
            $operation->update(['status' => 'ambiguous']);
            $this->trySync($domain);

            return $operation->fresh()->status === 'completed' ? 'completed' : 'ambiguous';
        } catch (OnlineNicException $exception) {
            $operation->update(['status' => 'failed', 'provider_code' => $exception->providerCode, 'provider_message' => $exception->providerMessage, 'completed_at' => now()]);

            return 'failed';
        }

        $operation->update(['status' => 'completed', 'svtrid' => $result->svtrid, 'provider_code' => $result->providerCode, 'provider_message' => $result->providerMessage, 'completed_at' => now()]);
        $this->trySync($domain);

        return 'completed';
    }

    private function trySync(Domain $domain): void
    {
        try {
            $this->sync->sync($domain);
        } catch (OnlineNicException) { /* write status remains authoritative; later refresh can sync */
        }
    }
}
