<?php

namespace App\Billing;

final class Money
{
    public static function normalize(string $amount): string
    {
        return bcadd($amount, '0', 2);
    }

    public static function round(string $amount): string
    {
        $negative = str_starts_with($amount, '-');
        $absolute = $negative ? substr($amount, 1) : $amount;
        $rounded = bcadd($absolute, '0.005', 2);

        return $negative ? '-'.$rounded : $rounded;
    }

    public static function add(string $left, string $right): string
    {
        return bcadd(self::normalize($left), self::normalize($right), 2);
    }

    public static function subtract(string $left, string $right): string
    {
        return bcsub(self::normalize($left), self::normalize($right), 2);
    }

    public static function fromDatabase(mixed $amount): string
    {
        if (is_string($amount) && preg_match('/^-?\d+(\.\d+)?$/', $amount) === 1) {
            return self::round($amount);
        }

        return self::round(sprintf('%.8F', (float) $amount));
    }
}
