<?php

namespace App\Domain\Domains\Services;

use App\Domain\Registrar\DTOs\DomainPrice;

final class CustomerDomainPricing
{
    public function __construct(private readonly ?float $markupPercent = null) {}

    public function customerPrice(DomainPrice $providerPrice): float
    {
        $markup = $this->markupPercent ?? (float) config('domain.registration_markup_percent', 20);

        return round($providerPrice->amount * (1 + ($markup / 100)), 2);
    }
}
