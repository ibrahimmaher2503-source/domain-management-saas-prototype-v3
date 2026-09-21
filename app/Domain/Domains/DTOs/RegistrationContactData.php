<?php

namespace App\Domain\Domains\DTOs;

final readonly class RegistrationContactData
{
    public function __construct(
        public string $name,
        public string $organization,
        public string $country,
        public string $province,
        public string $city,
        public string $street,
        public string $postalCode,
        public string $voice,
        public string $fax,
        public string $email,
    ) {}

    /** @return array<string, string> */
    public function toArray(): array
    {
        return ['name' => $this->name, 'organization' => $this->organization, 'country' => $this->country, 'province' => $this->province, 'city' => $this->city, 'street' => $this->street, 'postal_code' => $this->postalCode, 'voice' => $this->voice, 'fax' => $this->fax, 'email' => $this->email];
    }
}
