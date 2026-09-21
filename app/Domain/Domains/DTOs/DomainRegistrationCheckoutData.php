<?php

namespace App\Domain\Domains\DTOs;

use App\Domain\Billing\DTOs\PaymentBillingData;

final readonly class DomainRegistrationCheckoutData
{
    /** @param array<int, string> $nameservers */
    public function __construct(
        public string $domain,
        public int $period,
        public PaymentBillingData $paymentBillingData,
        public array $nameservers,
    ) {}
}
