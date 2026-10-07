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

            // Currencies a seller may choose to be paid in by this method (admin-set; default: the fee currency).
            $allowed = array_values(array_intersect(array_map('strtolower', (array) ($saved[$m]['currencies'] ?? [])), self::currencyOptions($local))) ?: [$currency];

            return [$m => [
                // Offered to sellers in this country (admin switch; on unless turned off).
                'enabled' => (bool) ($saved[$m]['enabled'] ?? true),
                'fixed_cents' => $fixed,
                // Smallest payout by this method, in the country's currency (0 = the country's minimum).
                'min_cents' => max(0, (int) ($saved[$m]['min_cents'] ?? 0)),
                'bps' => max(0, min(5000, (int) ($saved[$m]['bps'] ?? 0))),
                'currency' => $currency,
                'currencies' => $allowed,
                'local_fixed_cents' => $currency === $local ? $fixed : self::convert($fixed, $currency, $local),
            ]];
        })->all();
    }

    /**
     * The minimum payout for a seller: the country's minimum, or the method's
     * own minimum when higher (e.g. PayPal or a bank needs a larger amount).
     */
    public static function minFor(?string $market, ?string $method): int
    {
        $country = SellerLedger::minPayoutCents($market);

        return max($country, $method ? (int) (self::fees($market)[$method]['min_cents'] ?? 0) : 0);
    }

    /** The fee taken from a payout of $amountCents (country currency) by $method — never more than the payout. */
    public static function fee(?string $market, string $method, int $amountCents): int
    {
        $f = self::fees($market)[$method] ?? ['local_fixed_cents' => 0, 'bps' => 0];

        return min($amountCents, $f['local_fixed_cents'] + (int) round($amountCents * $f['bps'] / 10000));
    }

    /** Currencies a payout can be made in for a country: its own, USD, and the other selling countries'. */
    public static function currencyOptions(string $local): array
    {
        return array_values(array_unique(array_merge([$local, 'usd'], array_map(fn ($c) => Market::currency($c), Market::codes()))));
    }

    /** The currency this seller is paid in by $method: their choice if the method allows it, else the method's default. */
    public static function currencyFor(Seller $seller, string $method, ?string $market = null): string
    {
        $f = self::fees($market ?? $seller->shop?->market)[$method];
        $chosen = strtolower((string) (((array) $seller->payout_details)['payout_currency'] ?? ''));

        return in_array($chosen, $f['currencies'], true) ? $chosen : $f['currencies'][0];
    }

    /** What the seller gets after the fee, in the currency they're paid in, at today's rate. */
    public static function received(?string $market, string $method, int $amountCents, ?string $currency = null): array
    {
        $local = Market::currency($market);
        $currency ??= (self::fees($market)[$method] ?? ['currency' => $local])['currency'];
        $net = $amountCents - self::fee($market, $method, $amountCents);

        return ['currency' => $currency, 'cents' => self::convert($net, $local, $currency)];
    }

    /** Rate from the country's currency to each currency a payout can be made in. */
    public static function rates(string $market): array
    {
        $local = Market::currency($market);

        return collect(self::currencyOptions($local))->mapWithKeys(fn ($c) => [$c => $c === $local ? 1.0 : Fx::mid($c) / Fx::mid($local)])->all();
    }

    /** Plain exchange rate for payouts — no buyer currency margin. */
    private static function convert(int $cents, string $from, string $to): int
    {
        return strtolower($from) === strtolower($to) ? $cents : (int) round($cents * Fx::mid($to) / Fx::mid($from));
    }

    /** Can this seller be paid with their chosen method? Null = yes, else why not. */
    public static function blocker(Seller $seller): ?string
    {
        $fees = self::fees($seller->shop?->market);
        if ($seller->payout_method && ! ($fees[$seller->payout_method]['enabled'] ?? true)) {
            return ($seller->payout_method === 'paypal' ? 'PayPal' : 'Bank transfer').' payouts aren’t offered in your country right now — choose another way to be paid.';
        }
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
