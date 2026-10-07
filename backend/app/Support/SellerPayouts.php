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

    /** @return array{bank: array{fixed_cents: int, bps: int}, paypal: array{fixed_cents: int, bps: int}} */
    public static function fees(?string $market = null): array
    {
        $market = $market === null ? Market::home() : strtoupper($market);
        $saved = (array) (((array) Setting::get('payout_fees', []))[$market] ?? []);

        return collect(self::METHODS)->mapWithKeys(fn ($m) => [$m => [
            'fixed_cents' => max(0, (int) ($saved[$m]['fixed_cents'] ?? 0)),
            'bps' => max(0, min(5000, (int) ($saved[$m]['bps'] ?? 0))),
        ]])->all();
    }

    /** The fee taken from a payout of $amountCents by $method (never more than the payout). */
    public static function fee(?string $market, string $method, int $amountCents): int
    {
        $f = self::fees($market)[$method] ?? ['fixed_cents' => 0, 'bps' => 0];

        return min($amountCents, $f['fixed_cents'] + (int) round($amountCents * $f['bps'] / 10000));
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
