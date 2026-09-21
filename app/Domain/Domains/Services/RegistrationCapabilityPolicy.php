<?php

namespace App\Domain\Domains\Services;

final readonly class RegistrationCapabilityPolicy
{
    /** @param array<int, int> $periods */
    public function __construct(public string $tld, public int $domainType, public array $periods, public int $minNameservers = 2, public int $maxNameservers = 6) {}

    public function supportsPeriod(int $period): bool
    {
        return in_array($period, $this->periods, true);
    }
}
