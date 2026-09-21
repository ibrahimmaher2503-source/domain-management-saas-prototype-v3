<?php

namespace App\Domain\Domains\Services;

use App\Domain\Registrar\DTOs\DomainPrice;

final class CustomerDomainPricing
{
    public function __construct(private readonly ?string $markupPercent = null) {}

    public function customerPrice(DomainPrice $providerPrice): string
    {
        $markup = $this->markupPercent ?? (string) config('domain.registration_markup_percent', '20');
        if (! preg_match('/^\d+$/', $markup)) {
            throw new \InvalidArgumentException('Registration markup must be a whole percentage.');
        }
        if (! preg_match('/^\d+(?:\.\d{1,2})?$/', $providerPrice->amount)) {
            throw new \InvalidArgumentException('Provider price must be a decimal string.');
        }
        [$whole, $fraction] = array_pad(explode('.', $providerPrice->amount, 2), 2, '0');
        $cents = ((int) $whole * 100) + (int) str_pad(substr($fraction, 0, 2), 2, '0');
        $totalCents = intdiv($cents * (100 + (int) $markup) + 50, 100);

        return number_format($totalCents / 100, 2, '.', '');
    }
}
