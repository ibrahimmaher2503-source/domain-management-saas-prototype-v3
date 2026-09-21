<?php

namespace App\Domain\Domains\Services;

use App\Domain\Domains\DTOs\DomainSearchResult;
use App\Domain\Registrar\Contracts\RegistrarGateway;
use App\Domain\Registrar\DTOs\CheckDomainData;
use App\Domain\Registrar\DTOs\DomainPriceQuery;
use InvalidArgumentException;

final class DomainSearchService
{
    public function __construct(private readonly RegistrarGateway $registrar, private readonly CustomerDomainPricing $pricing) {}

    public function search(string $input, int $period = 1): DomainSearchResult
    {
        $domain = $this->normalize($input);
        if ($period < 1 || $period > 10) {
            throw new InvalidArgumentException('Registration period must be between 1 and 10 years.');
        }
        $availability = $this->registrar->checkDomain(new CheckDomainData($domain));
        $providerPrice = $availability->available ? $this->registrar->getDomainPrice(new DomainPriceQuery($domain, 'registration', $period)) : null;

        return new DomainSearchResult($availability, $providerPrice, $providerPrice ? $this->pricing->customerPrice($providerPrice) : null, $period);
    }

    public function normalize(string $input): string
    {
        $domain = strtolower(trim($input));
        if ($domain === '' || strlen($domain) > 253 || ! preg_match('/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/', $domain)) {
            throw new InvalidArgumentException('Enter a valid domain name, including its TLD.');
        }

        return $domain;
    }
}
