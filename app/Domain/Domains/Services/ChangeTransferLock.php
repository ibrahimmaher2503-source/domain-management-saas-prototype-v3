<?php

namespace App\Domain\Domains\Services;

use App\Domain\Registrar\Contracts\RegistrarGateway;
use App\Domain\Registrar\DTOs\TransferLockData;
use App\Integrations\OnlineNic\Exceptions\OnlineNicException;
use App\Integrations\OnlineNic\Exceptions\ProviderAmbiguousResponse;
use App\Integrations\OnlineNic\OnlineNicTransactionIdGenerator;
use App\Models\Domain;
use App\Models\RegistrarOperation;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class ChangeTransferLock
{
    public function __construct(private readonly RegistrarGateway $registrar, private readonly SyncDomainFromRegistrar $sync, private readonly OnlineNicTransactionIdGenerator $transactions) {}

    public function change(Domain $domain, bool $locked): string
    {
        if ($domain->provider !== 'onlinenic' || $domain->tld !== 'com' || $domain->status !== 'active') {
            throw new InvalidArgumentException('Transfer lock changes are unavailable for this domain.');
        }

        $operation = DB::transaction(function () use ($domain, $locked): RegistrarOperation|string {
            $domain = Domain::query()->whereKey($domain->id)->lockForUpdate()->firstOrFail();
            if ($domain->registrarOperations()->where('operation', 'set_transfer_lock')->whereIn('status', ['pending', 'ambiguous'])->exists()) {
                return 'blocked';
            }
            if ($domain->transfer_locked === $locked) {
                return 'unchanged';
            }

            return $domain->registrarOperations()->create([
                'user_id' => $domain->user_id, 'order_id' => $domain->order_id,
                'provider' => $domain->provider, 'operation' => 'set_transfer_lock',
                'cltrid' => $this->transactions->generate(), 'status' => 'pending',
                'safe_request_metadata' => ['locked' => $locked], 'started_at' => now(),
            ]);
        });
        if (is_string($operation)) {
            return $operation;
        }

        try {
            $result = $this->registrar->setTransferLock(new TransferLockData($domain->name, $locked), $operation->cltrid);
        } catch (ProviderAmbiguousResponse) {
            $operation->update(['status' => 'ambiguous']);
            $this->trySync($domain);

            return $operation->fresh()->status === 'completed' ? 'completed' : 'ambiguous';
        } catch (OnlineNicException $exception) {
            $operation->update(['status' => 'failed', 'provider_code' => $exception->providerCode, 'provider_message' => $exception->providerMessage, 'completed_at' => now()]);

            return 'failed';
        }

        DB::transaction(function () use ($domain, $locked, $operation, $result): void {
            Domain::query()->whereKey($domain->id)->lockForUpdate()->firstOrFail()->update(['transfer_locked' => $locked]);
            $operation->update(['status' => 'completed', 'svtrid' => $result->svtrid, 'provider_code' => $result->providerCode, 'provider_message' => $result->providerMessage, 'completed_at' => now()]);
        });
        $this->trySync($domain);

        return 'completed';
    }

    private function trySync(Domain $domain): void
    {
        try {
            $this->sync->sync($domain);
        } catch (OnlineNicException) { /* confirmed write remains authoritative; later refresh can sync */
        }
    }
}
