<?php

namespace App\Integrations\Paymob\DTOs;

final readonly class PaymobPaymentIntention
{
    public function __construct(
        public string $id,
        public int $orderId,
        public string $clientSecret,
        public string $reference,
        public string $status,
    ) {}
}
