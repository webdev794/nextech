<?php

namespace App\Support;

use App\Models\Order;
use App\Models\SellerLedgerEntry;
use App\Models\Setting;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Static-helper facade over the seller commission + payout ledger, matching
 * the Geo/CheckoutFees/Courier convention rather than a container-bound
 * service. Entries are append-only — a shop's balance is always
 * SUM(amount_cents), never a mutated column.
 */
class SellerLedger
{
    /** Commission rate for a market (other markets fall back to the US rate until set). */
    public static function rate(?string $market = null): int
    {
        $home = (int) Setting::get('commission_rate_bps', config('commission.rate_bps'));
        $market = $market === null ? Market::home() : strtoupper($market);
        if (Market::usesLegacySettings($market)) {
            return $home;
        }
        $override = Setting::get('payouts_'.$market, []);

        return (int) ((is_array($override) ? $override : [])['commission_rate_bps'] ?? $home);
    }

    /**
     * A payout limit in a market's currency: the home market's settings, or
     * another market's defaults (config/markets.php) with admin overrides in
     * "payouts_<CODE>".
     */
    public static function marketLimit(string $key, ?string $market = null): int
    {
        $market = $market === null ? Market::home() : strtoupper($market);
        $defaults = Market::profile($market)['payouts'] ?? null;
        if (is_array($defaults)) {
            $override = Setting::get('payouts_'.$market, []);

            return (int) ((is_array($override) ? $override : [])[$key] ?? $defaults[$key] ?? 0);
        }

        return (int) Setting::get($key, config('commission.'.$key));
    }

    /** Minimum balance a payout can be recorded against — batches small amounts instead of paying out per order. */
    public static function minPayoutCents(?string $market = null): int
    {
        return self::marketLimit('min_payout_cents', $market);
    }

    /** Largest single payout — banks cap a single transfer, so bigger balances go out over several. */
    public static function maxPayoutCents(?string $market = null): int
    {
        return self::marketLimit('max_payout_cents', $market);
    }

    /** Total payouts allowed across all sellers of a market per day (0 = no cap). */
    public static function dailyPayoutCapCents(?string $market = null): int
    {
        return self::marketLimit('daily_payout_cap_cents', $market);
    }

    public static function paidOutTodayCents(?string $market = null): int
    {
        $market = $market === null ? Market::home() : strtoupper($market);

        return (int) -SellerLedgerEntry::query()
            ->where('type', 'payout_debit')
            ->where('created_at', '>=', now()->startOfDay())
            ->whereHas('shop', fn ($q) => $q->where('market', $market))
            ->sum('amount_cents');
    }

    /** How much more can be paid out today (in this market) before the daily cap; null = uncapped. */
    public static function dailyPayoutRemainingCents(?string $market = null): ?int
    {
        $cap = self::dailyPayoutCapCents($market);

        return $cap > 0 ? max(0, $cap - self::paidOutTodayCents($market)) : null;
    }

    /** Platform default return window (days after delivery) for products without their own. */
    public static function returnWindowDays(): int
    {
        return (int) Setting::get('return_window_days', config('commission.return_window_days'));
    }

    /** Longest return window a product may set (and so the longest earnings are held). */
    public static function maxReturnDays(): int
    {
        return (int) Setting::get('max_return_days', config('commission.max_return_days'));
    }

    /** The return window (days) that applies to this shop's items on an order. */
    public static function orderReturnDays(Order $order, int $shopId): int
    {
        $default = self::returnWindowDays();

        return (int) ($order->items
            ->where('shop_id', $shopId)
            // The window frozen on the line at checkout; older lines fall back
            // to the product's current window, then the platform default.
            ->map(fn ($item) => $item->return_days ?? $item->product?->return_days ?? $default)
            ->max() ?? $default);
    }

    /**
     * When this shop's earnings on an order stop being returnable: delivery
     * date + the longest return window among the shop's items on that order.
     * Null while the order hasn't been delivered yet (still held).
     */
    public static function releaseDate(Order $order, int $shopId): ?Carbon
    {
        if ($order->status === 'cancelled') {
            return $order->updated_at; // credit and refund cancel out; nothing left to hold
        }
        // Lines the seller shipped themselves are "delivered" when all their
        // packages are. Otherwise completed orders from before delivered_at was
        // recorded fall back to when they were last updated (marked completed).
        $shipsOwn = $order->items->where('shop_id', $shopId)->contains(fn ($i) => $i->fulfilled_by === 'seller');
        $deliveredAt = $shipsOwn
            ? SellerFulfillment::shopDeliveredAt($order, $shopId)
            : ($order->delivered_at ?? ($order->status === 'completed' ? $order->updated_at : null));
        if (! $deliveredAt) {
            return null;
        }

        $release = $deliveredAt->copy()->addDays(self::orderReturnDays($order, $shopId));
        // Shipped abroad: also held through the products' warranty (claims are
        // harder to settle across borders), until the later of the two.
        if ($order->market && self::shopMarket($shopId) !== $order->market) {
            $months = $order->items->where('shop_id', $shopId)->map(fn ($i) => $i->product ? ProductCatalog::warrantyMonths($i->product) : 0)->max() ?? 0;
            if ($months > 0) {
                $release = $release->max($deliveredAt->copy()->addMonths($months));
            }
        }

        return $release;
    }

    /**
     * The shop's balance split into what can be paid out now ("available") and
     * what's still held for returns ("pending"), plus the held orders and when
     * each releases. Payouts and fees (no order) and anything on a cleared
     * order count as available; credits and refunds on an order still inside
     * its return window (or not yet delivered) are pending. A sale credit whose
     * order record is gone can't be checked against a return window, so it's
     * held too (order_id null) until admin sorts it out.
     *
     * @return array{available_cents: int, pending_cents: int, pending: list<array<string, mixed>>}
     */
    public static function breakdown(Shop $shop): array
    {
        $entries = SellerLedgerEntry::where('shop_id', $shop->id)->get(['order_id', 'type', 'amount_cents']);
        $orderIds = $entries->pluck('order_id')->filter()->unique();
        $orders = Order::query()
            ->whereIn('id', $orderIds)
            ->with(['items' => fn ($q) => $q->where('shop_id', $shop->id), 'items.product:id,return_days,product_details'])
            ->get(['id', 'market', 'status', 'delivered_at', 'updated_at'])
            ->keyBy('id');

        $now = now();
        $available = 0;
        $pendingByOrder = [];
        foreach ($entries as $entry) {
            $order = $entry->order_id ? $orders->get($entry->order_id) : null;
            $release = $order ? self::releaseDate($order, $shop->id) : null;

            if (! $entry->order_id && $entry->type === 'order_credit') {
                $pendingByOrder['missing'] ??= ['order_id' => null, 'amount_cents' => 0, 'releases_at' => null, 'return_days' => null, 'delivered' => false, 'order_missing' => true];
                $pendingByOrder['missing']['amount_cents'] += (int) $entry->amount_cents;
            } elseif (! $entry->order_id || ($release && $release->lte($now))) {
                $available += (int) $entry->amount_cents;
            } else {
                $pendingByOrder[$entry->order_id] ??= ['order_id' => $entry->order_id, 'amount_cents' => 0, 'releases_at' => $release, 'return_days' => $order ? self::orderReturnDays($order, $shop->id) : null, 'delivered' => $order && ($order->delivered_at || $order->status === 'completed')];
                $pendingByOrder[$entry->order_id]['amount_cents'] += (int) $entry->amount_cents;
            }
        }

        $pending = array_values(array_filter($pendingByOrder, fn ($p) => $p['amount_cents'] !== 0));
        usort($pending, fn ($a, $b) => ($a['releases_at']?->timestamp ?? PHP_INT_MAX) <=> ($b['releases_at']?->timestamp ?? PHP_INT_MAX));

        return [
            'available_cents' => $available,
            'pending_cents' => array_sum(array_column($pending, 'amount_cents')),
            'pending' => $pending,
        ];
    }

    public static function availableCents(Shop $shop): int
    {
        return self::breakdown($shop)['available_cents'];
    }

    /** What a seller can request right now: their cleared balance, capped at one transfer's max. */
    public static function requestableCents(Shop $shop): int
    {
        return max(0, min(self::availableCents($shop), self::maxPayoutCents($shop->market)));
    }

    /**
     * One order_credit entry per shop represented on the order (skipping a
     * null shop_id — NexTech's own inventory), each for that shop's subtotal
     * share minus commission. Idempotent: a no-op if this order already has a
     * credit, since payment_status can flip to 'paid' from more than one call
     * site (Stripe webhook, intent() reconciliation, COD cash-collected via
     * either the admin or rider app, or a gift card fully covering the order)
     * and this must only ever fire once per order.
     */
    public static function creditForOrder(Order $order): void
    {
        DB::transaction(function () use ($order): void {
            $exists = SellerLedgerEntry::where('order_id', $order->id)
                ->where('type', 'order_credit')
                ->lockForUpdate()
                ->exists();

            if ($exists) {
                return;
            }

            $items = $order->items()->whereNotNull('shop_id')->get();
            if ($items->isEmpty()) {
                return;
            }

            foreach ($items->groupBy('shop_id') as $shopId => $shopItems) {
                $subtotal = self::shopLinesCents($order, (int) $shopId, $shopItems);
                if ($subtotal <= 0) {
                    continue;
                }

                $commission = (int) round($subtotal * self::shopRate($order, (int) $shopId) / 10000);
                // The shipping the buyer paid a seller who ships it themselves goes
                // to that seller (who pays the courier), commission-free — the
                // seller's own fee in their currency when shipped abroad.
                $shipping = $order->shopShipping()->where('shop_id', $shopId)->first();
                $abroad = self::shopMarket((int) $shopId) !== $order->market;
                $shippingCents = $abroad ? (int) ($shipping?->seller_fee_cents ?? 0) + (int) ($shipping?->seller_paperwork_cents ?? 0) : (int) ($shipping?->fee_cents ?? 0);

                SellerLedgerEntry::create([
                    'shop_id' => $shopId,
                    'order_id' => $order->id,
                    'type' => 'order_credit',
                    'amount_cents' => $subtotal - $commission + $shippingCents,
                    'commission_cents' => $commission,
                    'note' => $shippingCents > 0 ? 'Includes '.($abroad ? 'international ' : '').'shipping '.Money::format($shippingCents, Market::currency(self::shopMarket((int) $shopId))) : null,
                ]);

                self::withhold($order, (int) $shopId, self::taxableCents($shopItems), 1);
            }
        });
    }

    /**
     * Splits a refund pro-rata across the order's shops by each shop's share
     * of the order subtotal, netting out the commission on the refunded
     * share too (the platform doesn't keep commission on money given back).
     * Called with this refund's own amount — not the order's cumulative
     * refunded total — so it's safe to call once per partial refund.
     */
    public static function returnPickupFeeCents(?string $market = null): int
    {
        return self::marketLimit('return_pickup_fee_cents', $market);
    }

    /** Postage for a NexTech-bought label when no real courier quotes one. */
    public static function labelPostageCents(?string $market = null): int
    {
        return self::marketLimit('label_postage_cents', $market);
    }

    /**
     * Take a refund back out of the seller(s) whose items were refunded — the
     * item value only, less the commission they'd paid on it (tax, delivery
     * and handling refunded to the customer are never charged here). With
     * $itemIds, exactly those lines are charged; with only an amount, it's
     * spread across the order's seller lines by value, capped at the items'
     * total. Applies to card refunds and store-credit (gift card) refunds.
     *
     * @param  list<int>  $itemIds
     */
    public static function debitForRefund(Order $order, int $refundAmountCents, array $itemIds = []): void
    {
        if ($refundAmountCents <= 0) {
            return;
        }

        $orderSubtotal = (int) $order->subtotal_cents;
        $items = $order->items()->whereNotNull('shop_id')->get();
        if ($orderSubtotal <= 0 || $items->isEmpty()) {
            return;
        }

        $itemPool = min($refundAmountCents, $orderSubtotal);

        foreach ($items->groupBy('shop_id') as $shopId => $shopItems) {
            $refundShareOrder = $itemIds
                ? (int) $shopItems->whereIn('id', $itemIds)->sum('line_total_cents')
                : (int) round($itemPool * $shopItems->sum('line_total_cents') / $orderSubtotal);
            if ($refundShareOrder <= 0) {
                continue;
            }
            $refundShare = $itemIds
                ? self::shopLinesCents($order, (int) $shopId, $shopItems->whereIn('id', $itemIds))
                : self::toShop($order, (int) $shopId, $refundShareOrder);

            $commissionBack = (int) round($refundShare * self::shopRate($order, (int) $shopId) / 10000);

            SellerLedgerEntry::create([
                'shop_id' => $shopId,
                'order_id' => $order->id,
                'type' => 'refund_debit',
                'amount_cents' => -($refundShare - $commissionBack),
                'commission_cents' => $commissionBack,
            ]);

            // Tax withheld on the refunded value comes back (net-of-returns basis).
            $gross = (int) $shopItems->sum('line_total_cents');
            if ($gross > 0) {
                self::withhold($order, (int) $shopId, (int) round(self::taxableCents($shopItems) * $refundShareOrder / $gross), -1);
            }
        }
    }

    /**
     * Costs of a return charged to the seller(s) involved, when the admin
     * ticks them on the refund: the pickup fee for collecting the item (per
     * return, per seller), and the seller's share of the order's delivery fee
     * (at most once per order — the original delivery isn't charged twice).
     * Shops involved = those whose items were refunded (or every seller on
     * the order when refunding by amount).
     *
     * @param  list<int>  $itemIds
     */
    public static function chargeReturnCosts(Order $order, array $itemIds, bool $pickup, bool $delivery): void
    {
        if (! $pickup && ! $delivery) {
            return;
        }

        $items = $order->items()->whereNotNull('shop_id')->get();
        $orderSubtotal = (int) $order->subtotal_cents;
        $shopIds = ($itemIds ? $items->whereIn('id', $itemIds) : $items)->pluck('shop_id')->unique();

        foreach ($shopIds as $shopId) {
            $pickupFee = self::toShop($order, (int) $shopId, self::returnPickupFeeCents($order->market));
            if ($pickup && $pickupFee > 0) {
                SellerLedgerEntry::create([
                    'shop_id' => $shopId,
                    'order_id' => $order->id,
                    'type' => 'return_pickup_fee',
                    'amount_cents' => -$pickupFee,
                ]);
            }

            $alreadyCharged = SellerLedgerEntry::where('shop_id', $shopId)->where('order_id', $order->id)->where('type', 'delivery_fee_charge')->exists();
            if ($delivery && ! $alreadyCharged && $orderSubtotal > 0 && (int) $order->delivery_fee_cents > 0) {
                $share = self::toShop($order, (int) $shopId, (int) round($order->delivery_fee_cents * $items->where('shop_id', $shopId)->sum('line_total_cents') / $orderSubtotal));
                if ($share > 0) {
                    SellerLedgerEntry::create([
                        'shop_id' => $shopId,
                        'order_id' => $order->id,
                        'type' => 'delivery_fee_charge',
                        'amount_cents' => -$share,
                    ]);
                }
            }
        }
    }

    /** The value of these lines net of the GST included in their price. */
    private static function taxableCents($items): int
    {
        return (int) $items->sum(fn ($item) => (int) $item->line_total_cents - Market::includedTaxCents((int) $item->line_total_cents, (int) $item->gst_rate_bps));
    }

    /**
     * Statutory withholding for the shop's market (India: TCS under GST sec.
     * 52 and TDS under sec. 194-O) on a taxable value — $sign -1 reverses it
     * for a refund. One ledger entry per withholding type.
     */
    private static function withhold(Order $order, int $shopId, int $taxableCents, int $sign): void
    {
        $shop = Shop::find($shopId);
        $taxableCents = self::toShop($order, $shopId, $taxableCents);
        foreach ((array) (Market::profile($shop?->market)['withholding'] ?? []) as $type => $rule) {
            $amount = (int) round($taxableCents * (int) $rule['rate_bps'] / 10000);
            if ($amount > 0) {
                SellerLedgerEntry::create([
                    'shop_id' => $shopId,
                    'order_id' => $order->id,
                    'type' => $type,
                    'amount_cents' => -$sign * $amount,
                    'note' => ($sign < 0 ? 'Reversal on refund — ' : '').$rule['label'].' on '.Money::format($taxableCents, Market::currency($shop?->market)),
                ]);
            }
        }
    }

    /**
     * An amount in the order's currency, in the shop's own currency: cross-
     * border orders divide by the rate frozen on the order (Fx), so the seller
     * gets back exactly their listed price and the margin stays with the
     * platform. Same-currency orders pass through unchanged.
     */
    public static function toShop(Order $order, int $shopId, int $cents): int
    {
        $rates = (array) $order->fx_rates;
        if (! $rates) {
            return $cents;
        }
        $multiplier = (float) ($rates[strtolower(Market::currency(self::shopMarket($shopId)))] ?? 0);

        return $multiplier > 0 ? (int) round($cents / $multiplier) : $cents;
    }

    /** These lines' value in the shop's currency: the frozen seller price on imports, else the line totals. */
    private static function shopLinesCents(Order $order, int $shopId, $items): int
    {
        return (int) $items->sum(fn ($item) => $item->seller_line_total_cents !== null
            ? (int) $item->seller_line_total_cents
            : self::toShop($order, $shopId, (int) $item->line_total_cents));
    }

    /**
     * Commission on one shop's share of an order: the new-seller rate while the
     * shop is in its first days (from approval), else the market's rate. Judged
     * at the order's date, so a later refund nets out the same rate the sale paid.
     */
    public static function shopRate(Order $order, int $shopId): int
    {
        $shop = Shop::with('seller:id,reviewed_at')->find($shopId);
        $newRate = self::newSellerRateBps();
        if ($newRate !== null) {
            $joined = $shop?->seller?->reviewed_at ?? $shop?->created_at;
            $at = $order->created_at ?? now();
            if ($joined && $at->lt($joined->copy()->addDays(self::newSellerDays()))) {
                return $newRate;
            }
        }

        // A rate kept from before an admin change (see lockShopRates), else the market's.
        return $shop?->commission_rate_bps ?? self::rate(self::commissionMarket($order, $shopId));
    }

    /** The commission rate this shop would pay on a sale made today (new-seller, kept or market rate). */
    public static function currentRateFor(Shop $shop): int
    {
        $order = new Order(['market' => $shop->market]);
        $order->created_at = now();

        return self::shopRate($order, $shop->id);
    }

    /** Every market's current commission rate, e.g. ['US' => 1000, 'IN' => 1200]. */
    public static function marketRates(): array
    {
        return collect(Market::codes())->mapWithKeys(fn ($code) => [$code => self::rate($code)])->all();
    }

    /**
     * After the admin changes commission: in each market whose rate moved,
     * existing shops either keep the rate they had ($applyToExisting false —
     * it's saved on the shop) or move to the new one (their kept rate is
     * cleared). Shops that join later always get the market's current rate.
     *
     * @param  array<string,int>  $before  marketRates() before the change
     * @param  list<string>  $forced  markets to move onto the current rate even if unchanged
     */
    public static function lockShopRates(array $before, bool $applyToExisting, array $forced = []): void
    {
        $after = self::marketRates();
        foreach ($after as $code => $rate) {
            $changed = isset($before[$code]) && $before[$code] !== $rate;
            if ($applyToExisting && ($changed || in_array($code, $forced, true))) {
                Shop::where('market', $code)->whereNotNull('commission_rate_bps')->update(['commission_rate_bps' => null]);
            } elseif (! $applyToExisting && $changed) {
                Shop::where('market', $code)->whereNull('commission_rate_bps')->update(['commission_rate_bps' => $before[$code]]);
            }
        }
    }

    /** Commission for new sellers (all markets); null = same as everyone else. */
    public static function newSellerRateBps(): ?int
    {
        $v = Setting::get('new_seller_commission_rate_bps');

        return $v === null || $v === '' ? null : (int) $v;
    }

    /** How long a seller counts as new, in days from approval. */
    public static function newSellerDays(): int
    {
        return (int) Setting::get('new_seller_days', 90);
    }

    /** Commission follows the seller's own market on cross-border orders. */
    private static function commissionMarket(Order $order, int $shopId): ?string
    {
        return $order->fx_rates ? self::shopMarket($shopId) : $order->market;
    }

    private static function shopMarket(int $shopId): ?string
    {
        static $markets = [];

        return $markets[$shopId] ??= Shop::whereKey($shopId)->value('market');
    }

    /** The withholding types for ledger labels, e.g. ['tcs_gst' => 'TCS (GST sec. 52)']. */
    public static function withholdingLabels(): array
    {
        return collect(config('markets', []))->flatMap(fn ($m) => collect($m['withholding'] ?? [])->map(fn ($r) => $r['label']))->all();
    }

    /** A manual settlement recorded by an admin — payouts happen outside the app (bank transfer etc). */
    public static function recordPayout(Shop $shop, int $amountCents, ?string $note, User $admin): SellerLedgerEntry
    {
        return SellerLedgerEntry::create([
            'shop_id' => $shop->id,
            'order_id' => null,
            'type' => 'payout_debit',
            'amount_cents' => -$amountCents,
            'commission_cents' => null,
            'note' => $note,
            'created_by' => $admin->id,
        ]);
    }
}
