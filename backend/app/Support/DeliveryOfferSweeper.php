<?php

namespace App\Support;

use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Finds delivery offers whose 60s Accept/Reject window has elapsed with no
 * response and re-offers them to the next-best rider (or drops them to the
 * shared pool). Called lazily from the rider and admin order endpoints, and
 * from the `riders:sweep-offers` scheduled command.
 */
final class DeliveryOfferSweeper
{
    /** Statuses where a live offer is meaningful. */
    private const OFFER_STATUSES = ['ready_for_delivery'];

    /**
     * Sweep every expired, unaccepted offer. Returns the number of orders acted
     * on. Each order is handled in its own transaction so one failure can't
     * abort the batch.
     */
    public static function sweep(): int
    {
        $ids = Order::query()
            ->whereNotNull('rider_offer_expires_at')
            ->whereNull('rider_accepted_at')
            ->where('rider_offer_expires_at', '<=', now())
            ->pluck('id');

        $handled = 0;

        foreach ($ids as $id) {
            try {
                if (self::sweepOne($id)) {
                    $handled++;
                }
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return $handled;
    }

    private static function sweepOne(int $orderId): bool
    {
        return (bool) DB::transaction(function () use ($orderId) {
            $order = Order::whereKey($orderId)->lockForUpdate()->first();

            if (! $order) {
                return false;
            }

            // Re-check under the lock: a rider may have accepted at the 59th
            // second, or another sweeper (lazy vs scheduled) may have handled it.
            if ($order->rider_accepted_at !== null
                || $order->rider_offer_expires_at === null
                || $order->rider_offer_expires_at->isFuture()) {
                return false;
            }

            // A stale marker on an order that has left the offerable states
            // (picked up without accepting, completed, cancelled): just wipe it.
            if (! in_array($order->status, self::OFFER_STATUSES, true)) {
                $order->forceFill(['rider_offer_expires_at' => null])->save();

                return false;
            }

            $missedRiderId = $order->delivery_partner_id;

            $declined = $order->rider_offer_declined_ids ?? [];
            if ($missedRiderId && ! in_array($missedRiderId, $declined, true)) {
                $declined[] = $missedRiderId;
            }

            $order->forceFill([
                'rider_offer_declined_ids' => $declined,
                'rider_offer_decline_count' => (int) $order->rider_offer_decline_count + ($missedRiderId ? 1 : 0),
                'delivery_partner_id' => null,
                'courier_name' => null,
                'rider_offer_expires_at' => null,
                'rider_accepted_at' => null,
            ])->save();

            if ($missedRiderId) {
                User::whereKey($missedRiderId)->increment('rider_missed_count');
            }

            // Next-best eligible rider, skipping everyone who has passed on it.
            // Null return -> the order stays unassigned = shared-pool fallback.
            RiderAssignment::assign($order->fresh(), $declined);

            return true;
        });
    }
}
