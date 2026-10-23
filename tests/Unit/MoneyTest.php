<?php

namespace Tests\Unit;

use App\Support\Money;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_canonical_form_and_rounding(): void
    {
        $this->assertSame('0.00081667', Money::of('0.000816666666'));
        $this->assertSame('1.00000000', Money::of(1));
        $this->assertSame('0.10000000', Money::of(0.1));
        $this->assertSame('10.01000000', Money::roundTo('10.005', 2));
        $this->assertSame('-10.01000000', Money::roundTo('-10.005', 2));
    }

    public function test_arithmetic_is_exact_where_floats_are_not(): void
    {
        $this->assertSame('0.30000000', Money::add('0.1', '0.2'));            // float: 0.30000000000000004
        $this->assertSame('0.00000001', Money::sub('0.00000002', '0.00000001'));
        $this->assertSame('0.49000000', Money::div('29400', '60000'));
    }

    public function test_percent_and_per_asset_precision(): void
    {
        $this->assertSame('1.00000000', Money::percent('50', '2', 2));
        $this->assertSame('0.03000000', Money::percent('1.5', '2', 2));      // 0.03 exactly
        $this->assertTrue(Money::fits('12.34', 2));
        $this->assertFalse(Money::fits('12.345', 2));
        $this->assertTrue(Money::fits('0.12345678', 8));
        $this->assertFalse(Money::fits('0.123456789', 8));
    }

    public function test_rejects_garbage(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Money::of('1e3');
    }

    public function test_minor_units_are_exact(): void
    {
        $this->assertSame(29, Money::toMinorUnits('0.29'));
        $this->assertSame(1999, Money::toMinorUnits('19.99'));
        $this->assertSame(100, Money::toMinorUnits('1'));
        $this->assertSame(150, Money::toMinorUnits('1.5000'));
        $this->assertSame(5, Money::toMinorUnits('0.05'));
        $this->assertSame(1000, Money::toMinorUnits('1.000', 3));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('badMinorUnits')]
    public function test_minor_units_reject_inexact_or_invalid(string $v): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Money::toMinorUnits($v);
    }

    public static function badMinorUnits(): array
    {
        return [['10.005'], ['-1.00'], ['abc'], ['1e3'], [''], ['99999999999999999999.00']];
    }
}
