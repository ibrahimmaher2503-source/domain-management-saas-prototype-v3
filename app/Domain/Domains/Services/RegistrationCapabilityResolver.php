<?php

namespace App\Domain\Domains\Services;

use App\Integrations\OnlineNic\Exceptions\UnsupportedCapability;
use App\Integrations\OnlineNic\OnlineNicTldResolver;

final class RegistrationCapabilityResolver
{
    public function __construct(private readonly OnlineNicTldResolver $tlds) {}

    public function forDomain(string $domain): RegistrationCapabilityPolicy
    {
        $tld = strtolower((string) ltrim(strrchr($domain, '.'), '.'));
        if ($tld !== 'com') {
            throw new UnsupportedCapability('Registration is not enabled for this TLD yet.');
        }

        return new RegistrationCapabilityPolicy('com', $this->tlds->domainType($domain), range(1, 10));
    }
}
