<?php

namespace App\Support;

use App\Models\Seller;
use App\Models\Setting;

/**
 * How a seller is paid: to their verified bank account or to PayPal, and the
 * payout fee the platform deducts for each method (per country, in that
 * country's currency: a fixed amount plus a percentage, admin-set).
 */
class SellerPayouts
{
    public const METHODS = ['bank', 'paypal'];

    /**
     * Withdrawal fees per method for a country. Each method also has the
     * currency it pays out in (e.g. PayPal pays Indian sellers in USD); its
     * fixed fee is in that currency. "local_fixed_cents" is the fixed fee in the
     * country's own currency at today's rate, which is what's deducted.
     *
     * @return array<string, array{fixed_cents: int, bps: int, currency: string, local_fixed_cents: int}>
     */
    public static function fees(?string $market = null): array
    {
        $market = $market === null ? Market::home() : strtoupper($market);
        $local = Market::currency($market);
        $saved = (array) (((array) Setting::get('payout_fees', []))[$market] ?? []);

        return collect(self::METHODS)->mapWithKeys(function ($m) use ($saved, $local) {
            $currency = strtolower((string) ($saved[$m]['currency'] ?? $local)) ?: $local;
            $fixed = max(0, (int) ($saved[$m]['fixed_cents'] ?? 0));

            return [$m => [
                'fixed_cents' => $fixed,
                'bps' => max(0, min(5000, (int) ($saved[$m]['bps'] ?? 0))),
                'currency' => $currency,
                'local_fixed_cents' => $currency === $local ? $fixed : self::convert($fixed, $currency, $local),
            ]];
        })->all();
    }

    /** The fee taken from a payout of $amountCents (country currency) by $method — never more than the payout. */
    public static function fee(?string $market, string $method, int $amountCents): int
    {
        $f = self::fees($market)[$method] ?? ['local_fixed_cents' => 0, 'bps' => 0];

        return min($amountCents, $f['local_fixed_cents'] + (int) round($amountCents * $f['bps'] / 10000));
    }

    /** What the seller gets in the method's payout currency (e.g. USD by PayPal), at today's rate. */
    public static function received(?string $market, string $method, int $amountCents): array
    {
        $local = Market::currency($market);
        $f = self::fees($market)[$method] ?? ['currency' => $local];
        $net = $amountCents - self::fee($market, $method, $amountCents);

        return ['currency' => $f['currency'], 'cents' => $f['currency'] === $local ? $net : self::convert($net, $local, $f['currency'])];
    }

    /** Plain exchange rate for payouts — no buyer currency margin. */
    private static function convert(int $cents, string $from, string $to): int
    {
        return strtolower($from) === strtolower($to) ? $cents : (int) round($cents * Fx::mid($to) / Fx::mid($from));
    }

    /** Can this seller be paid with their chosen method? Null = yes, else why not. */
    public static function blocker(Seller $seller): ?string
    {
        if ($seller->payout_method === 'paypal') {
            return empty(((array) $seller->payout_details)['paypal_email']) ? 'Add your PayPal email first.' : null;
        }
        if (! $seller->payout_method) {
            return 'Add your bank account or PayPal email first.';
        }
        if ($seller->bank_status !== 'linked' && $seller->shop && SellerRequirements::on($seller->shop, 'bank_verification')) {
            return $seller->bank_status === 'processing' ? 'Your bank account is still being verified (usually 1–2 business days).' : 'Add and verify your bank account first.';
        }

        return null;
    }
}
