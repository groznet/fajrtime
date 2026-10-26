<?php

namespace App\Support;

class Money
{
    /**
     * Kopecks → "14 250 ₽" / "712,50 ₽" (Russian style, non-breaking spaces).
     */
    public static function format(int $kopecks): string
    {
        $sign = $kopecks < 0 ? '−' : '';
        $kopecks = abs($kopecks);
        $decimals = $kopecks % 100 === 0 ? 0 : 2;

        return $sign.number_format($kopecks / 100, $decimals, ',', "\u{00A0}")."\u{00A0}₽";
    }

    /**
     * Kopecks → "500.00", the decimal string format payment gateways expect.
     */
    public static function toDecimal(int $kopecks): string
    {
        return sprintf('%d.%02d', intdiv($kopecks, 100), $kopecks % 100);
    }

    /**
     * "500.00" → 50000. Parsed as a string to avoid float rounding.
     */
    public static function fromDecimal(string $value): int
    {
        [$rubles, $kopecks] = array_pad(explode('.', $value, 2), 2, '0');

        return (int) $rubles * 100 + (int) str_pad(substr($kopecks, 0, 2), 2, '0');
    }
}
