<?php

namespace App\Support;

/** Formats minor-unit amounts for messages ("$12.50", "-₹1,299.00"). */
class Money
{
    public static function format(int $cents, ?string $currency = 'usd'): string
    {
        $sign = $cents < 0 ? '-' : '';
        $amount = abs($cents) / 100;

        return $sign.match (strtolower((string) $currency)) {
            'inr' => '₹'.self::indian($amount),
            default => '$'.number_format($amount, 2),
        };
    }

    /** Indian digit grouping: 1,23,45,678.00 */
    private static function indian(float $amount): string
    {
        [$whole, $fraction] = explode('.', number_format($amount, 2, '.', ''));
        $last3 = substr($whole, -3);
        $rest = substr($whole, 0, -3);
        $grouped = $rest === '' ? $last3 : preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest).','.$last3;

        return $grouped.'.'.$fraction;
    }
}
