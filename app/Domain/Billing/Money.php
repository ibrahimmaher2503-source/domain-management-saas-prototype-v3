<?php

namespace App\Domain\Billing;

use InvalidArgumentException;

final class Money
{
    public static function toMinorUnits(string $amount): int
    {
        $amount = trim($amount);
        if (! preg_match('/^(?:0|[1-9]\d*)(?:\.\d{1,2})?$/', $amount)) {
            throw new InvalidArgumentException('Amount must be a non-negative decimal with up to two decimal places.');
        }

        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');

        return ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
    }
}
