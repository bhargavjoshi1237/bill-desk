<?php

namespace App\Support;

use NumberFormatter;

/**
 * Rupee arithmetic done in integer paise so totals never drift from
 * floating-point rounding. Amounts go in and come out as 2-decimal strings,
 * matching the decimal(14, 2) database columns.
 */
final class Money
{
    public static function toPaise(float|int|string|null $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

    public static function fromPaise(int $paise): string
    {
        $sign = $paise < 0 ? '-' : '';
        $paise = abs($paise);

        return $sign.intdiv($paise, 100).'.'.str_pad((string) ($paise % 100), 2, '0', STR_PAD_LEFT);
    }

    /**
     * $percent % of $amount, rounded half-up to the nearest paisa.
     */
    public static function percentOf(float|int|string|null $amount, float|int|string|null $percent): string
    {
        $paise = self::toPaise($amount);
        // Percentages are stored with 3 decimals, so scale them to an integer too.
        $milliPercent = (int) round(((float) $percent) * 1000);
        $product = $paise * $milliPercent;

        $result = intdiv(abs($product) + 50_000, 100_000);

        return self::fromPaise($product < 0 ? -$result : $result);
    }

    public static function add(float|int|string|null ...$amounts): string
    {
        return self::fromPaise(array_sum(array_map(self::toPaise(...), $amounts)));
    }

    public static function subtract(float|int|string|null $from, float|int|string|null $amount): string
    {
        return self::fromPaise(self::toPaise($from) - self::toPaise($amount));
    }

    /**
     * Indian-style currency, e.g. ₹1,02,500.00.
     */
    public static function format(float|int|string|null $amount, bool $withSymbol = true): string
    {
        $value = self::toPaise($amount) / 100;

        return $withSymbol
            ? self::currencyFormatter()->formatCurrency($value, 'INR')
            : self::decimalFormatter()->format($value);
    }

    public static function percent(float|int|string|null $percent): string
    {
        return rtrim(rtrim(number_format((float) $percent, 3, '.', ''), '0'), '.').'%';
    }

    private static function currencyFormatter(): NumberFormatter
    {
        static $formatter;

        return $formatter ??= new NumberFormatter('en_IN', NumberFormatter::CURRENCY);
    }

    private static function decimalFormatter(): NumberFormatter
    {
        static $formatter;

        if (! $formatter) {
            $formatter = new NumberFormatter('en_IN', NumberFormatter::DECIMAL);
            $formatter->setAttribute(NumberFormatter::MIN_FRACTION_DIGITS, 2);
            $formatter->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, 2);
        }

        return $formatter;
    }
}
