<?php

namespace Tests\Unit;

use App\Support\Money;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    /**
     * @return array<string, array{float|int|string, float|string, string}>
     */
    public static function percentages(): array
    {
        return [
            'sheet row 1 portal' => [100000, 1.5, '1500.00'],
            'sheet row 2 portal' => [50000, 1.7, '850.00'],
            'sheet row 3 service' => [80000, 2.5, '2000.00'],
            'rounds down below half a paisa' => [33333, 1.7, '566.66'],
            'rounds half a paisa up' => [10.25, 1.5, '0.15'],
            'three-decimal rate' => [100000, '1.475', '1475.00'],
            'paise in the bill' => [12345.67, 2.5, '308.64'],
            'zero rate' => [5000, 0, '0.00'],
            'string input' => ['80000.00', '1.500', '1200.00'],
        ];
    }

    #[DataProvider('percentages')]
    public function test_percent_of(float|int|string $amount, float|string $percent, string $expected): void
    {
        $this->assertSame($expected, Money::percentOf($amount, $percent));
    }

    public function test_addition_and_subtraction_are_exact(): void
    {
        // 0.1 + 0.2 is 0.30000000000000004 in floating point.
        $this->assertSame('0.30', Money::add(0.1, 0.2));
        $this->assertSame('102500.00', Money::add('100000.00', '2500.00'));
        $this->assertSame('-500.25', Money::subtract(1000, '1500.25'));
        $this->assertSame('0.00', Money::add());
    }

    public function test_paise_round_trip(): void
    {
        $this->assertSame(10250050, Money::toPaise('102500.50'));
        $this->assertSame('-0.05', Money::fromPaise(-5));
        $this->assertSame(0, Money::toPaise(null));
    }

    public function test_indian_number_formatting(): void
    {
        $this->assertSame('₹1,02,500.00', Money::format('102500'));
        $this->assertSame('₹12,34,567.89', Money::format(1234567.89));
        $this->assertSame('1,02,500.00', Money::format(102500, withSymbol: false));
        $this->assertSame('1.5%', Money::percent('1.500'));
        $this->assertSame('1.475%', Money::percent(1.475));
        $this->assertSame('2%', Money::percent(2));
    }
}
