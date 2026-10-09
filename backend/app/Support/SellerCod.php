<?php

namespace App\Support;

use App\Models\Setting;
use App\Models\Shop;

/**
 * Cash on delivery on orders a seller ships: the seller collects and keeps
 * the cash, and NexTech's commission and fees come out of their balance. That
 * only works while they have card sales to deduct from, so it's admin-controlled
 * (Settings → Seller shipping): off by default, only for approved sellers, or
 * all — and paused for any seller who owes NexTech more than the limit.
 */
class SellerCod
{
    /** off · approved (each seller as admin sets) · all · local = only on the seller's local deliveries (their riders carry it, up to the per-order maximum). */
    public const MODES = ['off', 'approved', 'all', 'local'];

    /** Default "owed" limit per market, in that market's currency (cents). */
    private const DEFAULT_MAX_OWED = ['US' => 10000, 'IN' => 500000];

    public static function mode(): string
    {
        $mode = (string) Setting::get('seller_cod_mode', 'approved');

        return in_array($mode, self::MODES, true) ? $mode : 'approved';
    }

    public static function maxOwedCents(string $market): int
    {
        $limits = (array) Setting::get('seller_cod_max_owed', []);

        return max(0, (int) ($limits[$market] ?? self::DEFAULT_MAX_OWED[$market] ?? 10000));
    }

    /** What the seller owes NexTech (a negative balance), 0 when nothing. */
    public static function owedCents(Shop $shop): int
    {
        return max(0, -$shop->balanceCents());
    }

    /** Why this seller can't offer cash on delivery right now (seller-facing), or null. */
    public static function sellerReason(Shop $shop): ?string
    {
        return match (true) {
            self::mode() === 'off' => \App\Support\Branding::name().' has switched off cash on delivery for all sellers for now. Buyers pay by card. Send '.\App\Support\Branding::name().' a message if you’d like it.',
            self::mode() === 'local' && ! self::localOn($shop) => 'Cash on delivery is only for your local deliveries (buyers near your shop). Turn on local deliveries first.',
            self::mode() === 'approved' && ! $shop->cod_approved => 'Cash on delivery needs '.\App\Support\Branding::name().'’s approval for your shop — message '.\App\Support\Branding::name().' to ask for it.',
            self::owedCents($shop) > self::maxOwedCents($shop->market) => 'Paused: you owe '.\App\Support\Branding::name().' '.Money::format(self::owedCents($shop), Market::currency($shop->market)).' from cash orders (more than the '.Money::format(self::maxOwedCents($shop->market), Market::currency($shop->market)).' limit). It switches back on once that’s settled from your card sales or paid to '.\App\Support\Branding::name().'.',
            default => null,
        };
    }

    /** The shop's local delivery is on (and not switched off by the store). */
    public static function localOn(Shop $shop): bool
    {
        return ! empty($shop->local_delivery) && ! SellerStores::blockedByAdmin($shop);
    }

    /** @return array{available: bool, reason: ?string, mode: string, approved: bool, owed_cents: int, max_owed_cents: int} */
    public static function status(Shop $shop): array
    {
        $reason = self::sellerReason($shop);

        return [
            'available' => $reason === null,
            'reason' => $reason,
            'mode' => self::mode(),
            'approved' => (bool) $shop->cod_approved,
            'owed_cents' => self::owedCents($shop),
            'max_owed_cents' => self::maxOwedCents($shop->market),
        ];
    }
}
