<?php

namespace App\Support;

use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\RiderAssigned;

/**
 * Picks the delivery rider for an order: among the riders linked to the order's
 * fulfilling store, the one nearest the store, with a nudge toward riders who
 * aren't already loaded up.
 */
class RiderAssignment
{
    /**
     * Each delivery a rider already has counts as this many extra km when
     * ranking, so an idle rider a bit further out beats a busy rider next door.
     */
    private const BUSY_PENALTY_KM = 6.0;

    /** Deliveries that count as "still on the rider's plate". */
    private const ACTIVE_STATUSES = ['ready_for_delivery', 'out_for_delivery'];

    /**
     * How long riders have to take a ready order (admin sets 1 minute – 1 day; default 5 hours —
     * electronics needn't go out the same hour). The nearest rider is offered it, every rider at
     * the store sees it with the same countdown, and when time runs out the store is told.
     */
    public const OFFER_TTL_SECONDS = 60;

    public static function offerSeconds(): int
    {
        return 60 * max(1, min(1440, (int) Setting::get('rider_offer_minutes', 300)));
    }

    /** One deadline per order for every rider: ready time + the riders' time. Offers never run past it. */
    public static function deadline(Order $order): \Illuminate\Support\Carbon
    {
        return ($order->ready_at ?? $order->updated_at ?? now())->copy()->addSeconds(self::offerSeconds());
    }

    public static function isEnabled(): bool
    {
        return (bool) Setting::get('rider_auto_assign', true);
    }

    /**
     * Assign a rider to the order and return them, or null when auto-assignment
     * is off, the order can't be auto-assigned, or no rider is eligible (the
     * order then stays in the first-come pool).
     *
     * The assignment is a time-boxed *offer*: `rider_offer_expires_at` is set and
     * `rider_accepted_at` left null until the rider accepts. Riders in
     * `$excludeRiderIds` (plus anyone already in the order's
     * `rider_offer_declined_ids`) are skipped, so a re-offer never lands back on
     * a rider who passed on it.
     *
     * @param  array<int>  $excludeRiderIds
     */
    public static function assign(Order $order, array $excludeRiderIds = []): ?User
    {
        if (! self::isEnabled() || $order->delivery_partner_id || ! $order->store_id) {
            return null;
        }
        // Past the shared deadline: the store has been told; riders can still pick it up from the pool.
        if ($order->status === 'ready_for_delivery' && self::deadline($order)->isPast()) {
            return null;
        }

        $store = $order->relationLoaded('store') ? $order->store : $order->store()->first();

        if (! $store || $store->latitude === null || $store->longitude === null) {
            return null;
        }

        $exclude = array_values(array_unique(array_merge(
            $order->rider_offer_declined_ids ?? [],
            $excludeRiderIds,
        )));

        $riders = User::query()
            ->where('is_rider', true)
            ->where('rider_is_active', true)
            ->where('rider_available', true)
            ->when($exclude, fn ($query) => $query->whereNotIn('users.id', $exclude))
            ->whereHas('stores', fn ($query) => $query->whereKey($store->id))
            ->withCount(['deliveries as active_deliveries' => fn ($query) => $query
                ->whereIn('status', self::ACTIVE_STATUSES)])
            ->get();

        $best = null;

        foreach ($riders as $rider) {
            // Paused for cash (a seller's, or over the store's own limit) — not offered anything.
            if (SellerRiderCash::blockedReason($rider)) {
                continue;
            }
            $location = $rider->riderLocation();
            if ($location === null) {
                continue; // no live fix and no base — can't place this rider
            }

            $km = Geo::haversineKm(
                $location['lat'], $location['lng'],
                (float) $store->latitude, (float) $store->longitude,
            );
            $score = $km + $rider->active_deliveries * self::BUSY_PENALTY_KM;

            if ($best === null || $score < $best['score']) {
                $best = ['rider' => $rider, 'score' => $score];
            }
        }

        if ($best === null) {
            // Nobody on shift (on leave, logged out, paused): a heads-up now; the order stays open to riders
            // who come online until the shared deadline, when the store is told nobody took it.
            if ($order->status === 'ready_for_delivery') {
                NeedsCourier::headsUp($order);
            }

            return null;
        }

        $order->update([
            'delivery_partner_id' => $best['rider']->id,
            'courier_name' => $best['rider']->name,
            'rider_offer_expires_at' => min(now()->addSeconds(self::offerSeconds()), self::deadline($order)),
            'rider_accepted_at' => null,
        ]);

        User::whereKey($best['rider']->id)->increment('rider_offers_count');

        $best['rider']->notify(RiderAssigned::forOrder($order));

        return $best['rider'];
    }
}
