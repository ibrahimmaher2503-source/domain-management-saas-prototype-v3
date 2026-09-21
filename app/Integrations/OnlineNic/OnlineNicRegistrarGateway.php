<?php

namespace App\Integrations\OnlineNic;

use App\Domain\Registrar\Contracts\RegistrarGateway;
use App\Domain\Registrar\DTOs\CheckDomainData;
use App\Domain\Registrar\DTOs\DomainAvailability;
use App\Domain\Registrar\DTOs\DomainPrice;
use App\Domain\Registrar\DTOs\DomainPriceQuery;
use App\Integrations\OnlineNic\Commands\CheckDomainCommand;
use App\Integrations\OnlineNic\Commands\GetDomainPriceCommand;
use App\Integrations\OnlineNic\Exceptions\InvalidProviderResponse;

final class OnlineNicRegistrarGateway implements RegistrarGateway
{
    public function __construct(private readonly OnlineNicClient $client, private readonly OnlineNicTldResolver $tlds) {}

    public function checkDomain(CheckDomainData $data): DomainAvailability
    {
        $this->client->ensureAuthenticated();
        $response = $this->client->execute(new CheckDomainCommand($data->domain, $this->tlds->domainType($data->domain)));
        $lookupKey = $this->stringValue($response->data['lookupkey'] ?? null);
        $premium = strtolower($this->stringValue($response->data['premium'] ?? null)) === 'true';

        return new DomainAvailability(
            domain: $this->stringValue($response->data['domain'] ?? $data->domain),
            available: $this->stringValue($response->data['avail'] ?? '0') === '1',
            premium: $premium,
            trademarkClaimRequired: $lookupKey !== null,
            lookupKey: $lookupKey,
            providerMetadata: array_filter(['code' => (string) $response->code, 'message' => $response->message]),
        );
    }

    public function getDomainPrice(DomainPriceQuery $query): DomainPrice
    {
        $this->client->ensureAuthenticated();
        $response = $this->client->execute(new GetDomainPriceCommand($query->domain, $this->tlds->domainType($query->domain), $query->period));
        $amount = $response->data['price'] ?? null;
        if (! is_string($amount) || ! preg_match('/^\d+(?:\.\d{1,2})?$/', $amount)) {
            throw new InvalidProviderResponse('OnlineNIC returned an invalid domain price.', $response->code, $response->message);
        }

        return new DomainPrice($query->domain, $amount, $query->period, $query->operation);
    }

    private function stringValue(mixed $value): ?string
    {
        return is_scalar($value) && (string) $value !== '' ? (string) $value : null;
    }
}
