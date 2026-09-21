<?php

namespace App\Domain\Domains\DTOs;

final readonly class DomainRegistrationCheckoutData
{
    /** @param array<int, string> $nameservers */
    public function __construct(
        public string $domain,
        public int $period,
        public RegistrationContactData $registrant,
        public RegistrationContactData $administrative,
        public RegistrationContactData $technical,
        public RegistrationContactData $billing,
        public array $nameservers,
    ) {}

    /** @return array<string, mixed> */
    public function registrationData(): array
    {
        return ['registrant' => $this->registrant->toArray(), 'administrative' => $this->administrative->toArray(), 'technical' => $this->technical->toArray(), 'billing' => $this->billing->toArray()];
    }
}
