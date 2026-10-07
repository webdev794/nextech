<?php

namespace App\Support;

use App\Models\Order;
use App\Models\OrderAddressChange;
use Illuminate\Support\Carbon;

/**
 * Seller Center -> Manage orders (modelled on Temu's): each order as the
 * seller sees it, with one of four statuses and the actions that need them.
 *
 *  - pending   — just placed (the first 30 minutes, while the buyer can still
 *                cancel or change it) or not paid yet: do not ship
 *  - unshipped — none of the seller's items have left yet
 *  - shipped   — partly or fully shipped
 *  - cancelled — cancelled or fully refunded
 */
class SellerOrders
{
    public const PENDING_MINUTES = 30;

    public const STATUSES = ['pending', 'unshipped', 'shipped', 'cancelled'];

    /** Order statuses after which NexTech has sent the order on its way. */
    private const NEXTECH_SHIPPED = ['out_for_delivery', 'completed'];

    public static function pendingUntil(Order $order): Carbon
    {
        return $order->created_at->copy()->addMinutes(self::PENDING_MINUTES);
    }

    /** Still inside the pending window (or unpaid) — not to be shipped yet. */
    public static function isPending(Order $order): bool
    {
        return $order->status === 'pending_payment' || self::pendingUntil($order)->isFuture();
    }

    public static function status(Order $order, int $shopId): string
    {
        if ($order->status === 'cancelled' || $order->payment_status === 'refunded') {
            return 'cancelled';
        }
        if (self::isPending($order)) {
            return 'pending';
        }
        $shipped = $order->packages->where('shop_id', $shopId)->isNotEmpty()
            || in_array($order->status, self::NEXTECH_SHIPPED, true);

        return $shipped ? 'shipped' : 'unshipped';
    }

    /** The buyer opened a support chat about this order (their own, not a seller thread). */
    public static function buyerContactedAt(Order $order): ?Carbon
    {
        return $order->supportThreads
            ->where('user_id', $order->user_id)
            ->reject(fn ($t) => str_starts_with((string) $t->issue_type, 'seller_'))
            ->max('last_message_at');
    }

    public static function pendingAddressChange(Order $order): ?OrderAddressChange
    {
        return $order->addressChanges->firstWhere('status', 'pending');
    }

    /**
     * Unshipped seller-shipped items whose ship-by date is today or already
     * past (or tomorrow) — ship now so the order isn't cancelled for a late shipment.
     */
    public static function delayRisk(Order $order, int $shopId, string $status): bool
    {
        if ($status !== 'unshipped') {
            return false;
        }
        $promise = $order->shopShipping->firstWhere('shop_id', $shopId);

        return $promise !== null && $promise->ship_by->lte(today()->addDay());
    }

    /**
     * Apply a buyer's address change to the order (keeps the phone and
     * coordinates-free fields the new address doesn't set).
     */
    public static function applyAddressChange(OrderAddressChange $change, ?int $shopId, ?int $userId): void
    {
        $order = $change->order;
        $order->update(['delivery_address' => array_merge((array) $order->delivery_address, $change->address)]);
        $change->update(['status' => 'approved', 'decided_by_shop_id' => $shopId, 'decided_by_user_id' => $userId, 'decided_at' => now()]);
    }

    /**
     * Stop a shipment the buyer may still change: an order in its pending
     * window, or one whose buyer asked for a new address that isn't decided yet.
     */
    public static function assertShippable(Order $order): void
    {
        abort_if(self::isPending($order), 422, 'This order is still pending — it can be shipped once it moves to Unshipped (about '.self::PENDING_MINUTES.' minutes after it was placed).');
        abort_if($order->addressChanges()->where('status', 'pending')->exists(), 422, 'The buyer asked to change the shipping address — accept or decline it in Manage orders first.');
    }

    /** Whether an order can still have its address changed: nothing has left for it yet. */
    public static function addressChangeable(Order $order): bool
    {
        return in_array($order->status, ['confirmed', 'packing', 'ready_for_delivery'], true)
            && $order->packages->isEmpty();
    }
}
