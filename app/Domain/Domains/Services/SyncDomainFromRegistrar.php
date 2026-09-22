<?php

namespace App\Domain\Domains\Services;

use App\Domain\Registrar\Contracts\RegistrarGateway;
use App\Domain\Registrar\DTOs\UpdateNameserversData;
use App\Integrations\OnlineNic\Exceptions\InvalidProviderResponse;
use App\Integrations\OnlineNic\OnlineNicTransactionIdGenerator;
use App\Models\Domain;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class SyncDomainFromRegistrar
{
    public function __construct(private readonly RegistrarGateway $registrar, private readonly OnlineNicTransactionIdGenerator $transactions) {}

    public function sync(Domain $domain): Domain
    {
        $info = $this->registrar->getDomainInfo($domain->name);
        if (strcasecmp($info->domain, $domain->name) !== 0) {
            throw new InvalidProviderResponse('Registrar returned another domain.');
        }

        $updates = ['provider_synced_at' => now()];
        foreach (['registered_at' => $info->registeredAt, 'expires_at' => $info->expiresAt] as $field => $value) {
            if ($value !== null && preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $value, $parts) && checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])) {
                $updates[$field] = sprintf('%04d-%02d-%02d', (int) $parts[1], (int) $parts[2], (int) $parts[3]);
            }
        }
        if ($info->providerStatus !== null && $info->providerStatus !== '') {
            $updates['provider_status'] = $info->providerStatus;
        }
        if ($info->transferLocked !== null) {
            $updates['transfer_locked'] = $info->transferLocked;
        }
        try {
            $nameservers = new UpdateNameserversData($domain->name, $info->nameservers);
            $updates['nameservers'] = $nameservers->nameservers;
        } catch (InvalidArgumentException) { /* omitted or invalid provider data is not confirmation */
        }

        return DB::transaction(function () use ($domain, $updates): Domain {
            $locked = Domain::query()->whereKey($domain->id)->lockForUpdate()->firstOrFail();
            $locked->update($updates);
            $locked->registrarOperations()->create([
                'user_id' => $locked->user_id, 'order_id' => $locked->order_id,
                'provider' => $locked->provider, 'operation' => 'domain_sync',
                'cltrid' => $this->transactions->generate(), 'status' => 'completed',
                'started_at' => now(), 'completed_at' => now(),
            ]);
            if (isset($updates['nameservers'])) {
                foreach ($locked->registrarOperations()->where('operation', 'update_nameservers')->where('status', 'ambiguous')->get() as $operation) {
                    $requested = $operation->safe_request_metadata['nameservers'] ?? [];
                    if ($this->sameNameservers($requested, $updates['nameservers'])) {
                        $operation->update(['status' => 'completed', 'completed_at' => now()]);
                    }
                }
            }
            if (array_key_exists('transfer_locked', $updates)) {
                foreach ($locked->registrarOperations()->where('operation', 'set_transfer_lock')->where('status', 'ambiguous')->get() as $operation) {
                    if (($operation->safe_request_metadata['locked'] ?? null) === $updates['transfer_locked']) {
                        $operation->update(['status' => 'completed', 'completed_at' => now()]);
                    }
                }
            }

            return $locked->fresh();
        });
    }

    /** @param array<int, string> $a */
    /** @param array<int, string> $b */
    public function sameNameservers(array $a, array $b): bool
    {
        $a = array_values(array_map('strtolower', $a));
        $b = array_values(array_map('strtolower', $b));
        sort($a);
        sort($b);

        return $a === $b;
    }
}
