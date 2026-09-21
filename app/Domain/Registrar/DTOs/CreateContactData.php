<?php

namespace App\Domain\Registrar\DTOs;

final readonly class CreateContactData
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
        public string $password,
        public int $domainType = 0,
    ) {}
}
