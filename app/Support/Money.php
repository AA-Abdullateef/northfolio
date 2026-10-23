<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Exact decimal arithmetic for ledger amounts (BCMath, strings only).
 *
 * Storage scale is 8 decimal places (transactions.amount DECIMAL(24,8)).
 * Every operation returns a canonical string with exactly SCALE decimals,
 * rounded half away from zero. Floats are never used for ledger math.
 * Per-asset display/input precision (e.g. USD = 2) is enforced with
 * Money::fits() and Money::roundTo().
 */
final class Money
{
    public const SCALE = 8;

    /** Canonicalise to SCALE decimals (rounds half away from zero if more are given). */
    public static function of(string|int|float|null $value): string
    {
        if ($value === null || $value === '') {
            return self::zero();
        }

        if (is_float($value)) {
            if (! is_finite($value)) {
                throw new InvalidArgumentException('Non-finite amount.');
            }
            $value = sprintf('%.12F', $value);
        }

        $value = trim((string) $value);

        if (! preg_match('/^-?\d+(\.\d+)?$/', $value)) {
            throw new InvalidArgumentException("Invalid decimal amount: {$value}");
        }

        return self::roundTo($value, self::SCALE);
    }

    public static function zero(): string
    {
        return '0.' . str_repeat('0', self::SCALE);
    }

    public static function add(string $a, string $b): string
    {
        return bcadd(self::of($a), self::of($b), self::SCALE);
    }

    public static function sub(string $a, string $b): string
    {
        return bcsub(self::of($a), self::of($b), self::SCALE);
    }

    /** a × b, rounded to SCALE. */
    public static function mul(string $a, string $b): string
    {
        return self::roundTo(bcmul(self::of($a), self::of($b), self::SCALE * 2), self::SCALE);
    }

    /** a ÷ b, rounded to SCALE. */
    public static function div(string $a, string $b): string
    {
        if (self::cmp($b, '0') === 0) {
            throw new InvalidArgumentException('Division by zero.');
        }

        return self::roundTo(bcdiv(self::of($a), self::of($b), self::SCALE + 4), self::SCALE);
    }

    /** amount × rate% (rate such as "2.50" means 2.5 percent), rounded to $places. */
    public static function percent(string $amount, string|float $ratePercent, int $places = self::SCALE): string
    {
        $raw = bcdiv(bcmul(self::of($amount), self::of($ratePercent), self::SCALE * 2), '100', self::SCALE * 2);

        return self::roundTo($raw, $places);
    }

    public static function cmp(string|int|float $a, string|int|float $b): int
    {
        return bccomp(self::of($a), self::of($b), self::SCALE);
    }

    public static function isPositive(string|int|float $a): bool
    {
        return self::cmp($a, 0) === 1;
    }

    /** Human display: thousands separators, trailing zeros trimmed, at least 2 decimals. */
    public static function display(string|int|float|null $value): string
    {
        $v = self::of($value);
        [$int, $frac] = explode('.', $v);
        $frac = rtrim($frac, '0');
        $frac = str_pad($frac, 2, '0');
        $neg  = str_starts_with($int, '-');
        $int  = number_format((int) ltrim($int, '-'), 0, '.', ',');

        return ($neg ? '-' : '') . $int . '.' . $frac;
    }

    /** Round half away from zero to $places decimals; result padded to SCALE decimals. */
    public static function roundTo(string $value, int $places): string
    {
        $negative = str_starts_with($value, '-');
        $abs      = ltrim($value, '-');
        $half     = '0.' . str_repeat('0', $places) . '5';
        $rounded  = bcadd($abs, $half, $places);
        $out      = bcadd($rounded, '0', self::SCALE); // pad to SCALE

        if ($negative && bccomp($out, '0', self::SCALE) !== 0) {
            $out = '-' . $out;
        }

        return $out;
    }

    /**
     * Convert a decimal amount to integer minor units (e.g. USD → cents) with no
     * float arithmetic. Refuses amounts that are not exactly representable
     * (more than $places significant decimals), negative, or not numeric.
     */
    public static function toMinorUnits(string $value, int $places = 2): int
    {
        $value = trim($value);

        if (! preg_match('/^\d+(\.\d+)?$/', $value) || ! self::fits($value, $places)) {
            throw new InvalidArgumentException("Amount {$value} cannot be expressed exactly in {$places} decimal places.");
        }

        $minor = bcmul(self::of($value), '1' . str_repeat('0', $places), 0);

        if (bccomp($minor, (string) PHP_INT_MAX, 0) > 0) {
            throw new InvalidArgumentException('Amount too large.');
        }

        return (int) $minor;
    }

    /** Does $value have no more than $places significant decimals? */
    public static function fits(string|int|float $value, int $places): bool
    {
        // Floats are accepted only for legacy callers; ledger code passes strings.
        $s = is_float($value) ? sprintf('%.12F', $value) : (string) $value;

        if (! preg_match('/^-?\d+(\.\d+)?$/', trim($s))) {
            return false;
        }

        $fraction = explode('.', $s)[1] ?? '';

        return strlen(rtrim($fraction, '0')) <= $places;
    }
}
