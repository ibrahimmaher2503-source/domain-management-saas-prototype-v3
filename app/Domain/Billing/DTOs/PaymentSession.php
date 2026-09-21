<?php

namespace App\Domain\Billing\DTOs;

final readonly class PaymentSession
{
    public function __construct(
        public string $checkoutUrl,
        public string $providerIntentionId,
        public int $providerOrderId,
        public string $clientSecret,
        public string $reference,
        public string $providerStatus,
    ) {}
}
