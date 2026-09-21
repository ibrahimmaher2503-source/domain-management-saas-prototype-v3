<?php

namespace Tests\Unit;

use App\Domain\Billing\Money;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class MoneyTest extends TestCase
{
    public function test_major_decimal_is_converted_without_float_math(): void
    {
        self::assertSame(10000, Money::toMinorUnits('100.00'));
        self::assertSame(1499, Money::toMinorUnits('14.99'));
        self::assertSame(500, Money::toMinorUnits('5'));
    }

    public function test_invalid_precision_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Money::toMinorUnits('1.001');
    }
}
