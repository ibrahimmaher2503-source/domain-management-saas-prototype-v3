<?php

namespace App\Integrations\OnlineNic;

use App\Domain\Registrar\Contracts\RegistrarGateway;
use App\Domain\Registrar\DTOs\CheckContactData;
use App\Domain\Registrar\DTOs\CheckDomainData;
use App\Domain\Registrar\DTOs\ContactResult;
use App\Domain\Registrar\DTOs\CreateContactData;
use App\Domain\Registrar\DTOs\DomainAvailability;
use App\Domain\Registrar\DTOs\DomainInfo;
use App\Domain\Registrar\DTOs\DomainPrice;
use App\Domain\Registrar\DTOs\DomainPriceQuery;
use App\Domain\Registrar\DTOs\DomainRegistrationData;
use App\Domain\Registrar\DTOs\RegistrationResult;
use App\Integrations\OnlineNic\Commands\CheckContactCommand;
use App\Integrations\OnlineNic\Commands\CheckDomainCommand;
use App\Integrations\OnlineNic\Commands\CreateContactCommand;
use App\Integrations\OnlineNic\Commands\CreateDomainCommand;
use App\Integrations\OnlineNic\Commands\GetDomainPriceCommand;
use App\Integrations\OnlineNic\Commands\InfoDomainCommand;
use App\Integrations\OnlineNic\Exceptions\InvalidProviderResponse;
use App\Integrations\OnlineNic\Exceptions\ProviderAmbiguousResponse;

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

    public function createContact(CreateContactData $data, string $cltrid): ContactResult
    {
        $this->client->ensureAuthenticated();
        $response = $this->client->execute(new CreateContactCommand($data), $cltrid);
        $this->requireCompletedWrite($response, $cltrid);
        $contactId = $this->stringValue($response->data['contactid'] ?? null);
        if ($contactId === null) {
            throw new ProviderAmbiguousResponse('OnlineNIC accepted contact creation without returning a contact ID.', $response->code, $response->message);
        }

        return new ContactResult($contactId, $response->cltrid, $response->svtrid, $response->code, $response->message);
    }

    public function checkContact(CheckContactData $data): bool
    {
        $this->client->ensureAuthenticated();
        $response = $this->client->execute(new CheckContactCommand($data));

        return $this->stringValue($response->data['avail'] ?? null) === '0';
    }

    public function registerDomain(DomainRegistrationData $data, string $cltrid): RegistrationResult
    {
        $this->client->ensureAuthenticated();
        $response = $this->client->execute(new CreateDomainCommand($data), $cltrid);
        $this->requireCompletedWrite($response, $cltrid);
        $domain = $this->stringValue($response->data['domain'] ?? null);
        if ($domain === null || strcasecmp($domain, $data->domain) !== 0) {
            throw new ProviderAmbiguousResponse('OnlineNIC did not confirm the requested domain.', $response->code, $response->message);
        }

        return new RegistrationResult($domain, $this->stringValue($response->data['reg_date'] ?? null), $this->stringValue($response->data['exp_date'] ?? null), $response->cltrid, $response->svtrid, $response->code, $response->message, $response->data);
    }

    public function getDomainInfo(string $domain): DomainInfo
    {
        $this->client->ensureAuthenticated();
        $response = $this->client->execute(new InfoDomainCommand($domain, $this->tlds->domainType($domain)));
        if ($response->code !== 1000) {
            throw new InvalidProviderResponse('OnlineNIC domain information was not confirmed.', $response->code, $response->message);
        }
        $confirmedDomain = $this->stringValue($response->data['domain'] ?? null);
        if ($confirmedDomain === null || strcasecmp($confirmedDomain, $domain) !== 0) {
            throw new InvalidProviderResponse('OnlineNIC did not confirm the requested domain.', $response->code, $response->message);
        }
        $dns = $response->data['dns'] ?? [];
        $nameservers = is_array($dns) ? array_values(array_map('strval', $dns)) : ($dns === '' ? [] : [(string) $dns]);

        return new DomainInfo($confirmedDomain, $this->stringValue($response->data['crDate'] ?? null), $this->stringValue($response->data['exDate'] ?? null), $nameservers, $this->stringValue($response->data['status'] ?? null), $response->cltrid, $response->svtrid, $response->code, $response->message);
    }

    private function requireCompletedWrite(OnlineNicResponse $response, string $cltrid): void
    {
        if ($response->code !== 1000 || $response->cltrid !== $cltrid) {
            throw new ProviderAmbiguousResponse('OnlineNIC write is not confirmed complete.', $response->code, $response->message);
        }
    }

    private function stringValue(mixed $value): ?string
    {
        return is_scalar($value) && (string) $value !== '' ? (string) $value : null;
    }
}
