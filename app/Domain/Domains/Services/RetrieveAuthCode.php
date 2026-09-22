<?php

namespace App\Domain\Domains\Services;

use App\Domain\Registrar\Contracts\RegistrarGateway;
use App\Integrations\OnlineNic\OnlineNicTransactionIdGenerator;
use App\Models\Domain;
use InvalidArgumentException;

final class RetrieveAuthCode
{
    public function __construct(private readonly RegistrarGateway $registrar, private readonly OnlineNicTransactionIdGenerator $transactions) {}

    public function retrieve(Domain $domain): string
    {
        if ($domain->provider !== 'onlinenic' || $domain->tld !== 'com' || $domain->status !== 'active') {
            throw new InvalidArgumentException('Transfer code retrieval is unavailable for this domain.');
        }

        $result = $this->registrar->getAuthCode($domain->name);
        $domain->registrarOperations()->create([
            'user_id' => $domain->user_id, 'order_id' => $domain->order_id,
            'provider' => $domain->provider, 'operation' => 'get_auth_code',
            'cltrid' => $result->cltrid ?: $this->transactions->generate(), 'svtrid' => $result->svtrid,
            'status' => 'completed', 'provider_code' => $result->providerCode,
            'started_at' => now(), 'completed_at' => now(),
        ]);

        return $result->authCode;
    }
}
