<?php

namespace App\Domain\Billing\DTOs;

final readonly class PaymentBillingData
{
    public function __construct(
        public string $firstName,
        public string $lastName,
        public string $email,
        public string $phoneNumber,
        public string $country,
        public string $city,
        public string $street,
        public string $state,
        public string $postalCode,
    ) {}

    /** @return array<string, string> */
    public function toArray(): array
    {
        return [
            'first_name' => $this->firstName, 'last_name' => $this->lastName,
            'email' => $this->email, 'phone_number' => $this->phoneNumber,
            'country' => $this->country, 'city' => $this->city,
            'street' => $this->street, 'state' => $this->state,
            'postal_code' => $this->postalCode,
        ];
    }
}
