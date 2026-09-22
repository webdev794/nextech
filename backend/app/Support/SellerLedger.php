<?php

namespace App\Support;

use App\Models\Order;
use App\Models\SellerLedgerEntry;
use App\Models\Setting;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Static-helper facade over the seller commission + payout ledger, matching
 * the Geo/CheckoutFees/Courier convention rather than a container-bound
 * service. Entries are append-only — a shop's balance is always
 * SUM(amount_cents), never a mutated column.
 */
class SellerLedger
{
    public static function rate(): int
    {
        return (int) Setting::get('commission_rate_bps', config('commission.rate_bps'));
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

            $rate = self::rate();

            foreach ($items->groupBy('shop_id') as $shopId => $shopItems) {
                $subtotal = (int) $shopItems->sum('line_total_cents');
                if ($subtotal <= 0) {
                    continue;
                }

                $commission = (int) round($subtotal * $rate / 10000);

                SellerLedgerEntry::create([
                    'shop_id' => $shopId,
                    'order_id' => $order->id,
                    'type' => 'order_credit',
                    'amount_cents' => $subtotal - $commission,
                    'commission_cents' => $commission,
                ]);
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
    public static function debitForRefund(Order $order, int $refundAmountCents): void
    {
        if ($refundAmountCents <= 0) {
            return;
        }

        $orderSubtotal = (int) $order->subtotal_cents;
        if ($orderSubtotal <= 0) {
            return;
        }

        $items = $order->items()->whereNotNull('shop_id')->get();
        if ($items->isEmpty()) {
            return;
        }

        $rate = self::rate();

        foreach ($items->groupBy('shop_id') as $shopId => $shopItems) {
            $shopSubtotal = (int) $shopItems->sum('line_total_cents');
            if ($shopSubtotal <= 0) {
                continue;
            }

            $refundShare = (int) round($refundAmountCents * $shopSubtotal / $orderSubtotal);
            if ($refundShare <= 0) {
                continue;
            }

            $commissionBack = (int) round($refundShare * $rate / 10000);

            SellerLedgerEntry::create([
                'shop_id' => $shopId,
                'order_id' => $order->id,
                'type' => 'refund_debit',
                'amount_cents' => -($refundShare - $commissionBack),
                'commission_cents' => $commissionBack,
            ]);
        }
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
