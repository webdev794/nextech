<?php

namespace App\Support;

use App\Models\OrderPackage;
use App\Models\Shop;
use App\Models\User;
use App\Notifications\RiderNotice;

/**
 * A seller's own-delivery packages and their riders: the seller gives a package
 * to one of their riders (or delivers it themselves); the rider sees it in the
 * Rider app and marks it delivered with the buyer's code. The store's admin can
 * watch every step but not change them — the order is the seller's.
 */
class SellerRiders
{
    /** A rider the seller can give orders to: linked to their store, an active rider. */
    public static function riderFor(Shop $shop, int $riderId): User
    {
        $store = SellerStores::ensure($shop);
        $rider = $store->riders()->where('users.id', $riderId)->where('is_rider', true)->first();
        abort_unless($rider, 422, 'That rider doesn’t deliver for your store.');
        abort_unless($rider->rider_is_active, 422, "{$rider->name} is paused — choose another rider or deliver it yourself.");
        abort_if((bool) $rider->pivot?->cash_paused_at, 422, "{$rider->name} is paused until they hand over the cash they hold — mark it received first (or choose Later).");
        $why = SellerRiderCash::blockedReason($rider);
        abort_if($why !== null, 422, "{$rider->name} is paused in every store until they {$why}.");

        return $rider;
    }

    /**
     * The best free rider for the seller's store right now: on shift, not paused
     * (for cash anywhere), nearest the store, nudged toward riders with fewer
     * deliveries out. Null when none is free.
     */
    public static function nearestFree(Shop $shop): ?User
    {
        $store = SellerStores::ensure($shop);
        $best = null;
        foreach ($store->riders()->where('is_rider', true)->where('rider_is_active', true)->where('rider_available', true)->get() as $rider) {
            if ($rider->pivot?->cash_paused_at || SellerRiderCash::blockedReason($rider) || ! $rider->currentShift()) {
                continue;
            }
            $out = OrderPackage::query()->where('rider_id', $rider->id)->whereNotIn('status', ['delivered', 'returned', 'lost'])->count();
            $at = $rider->riderLocation();
            $km = $at && $store->hasCoordinates() ? Geo::haversineKm($at['lat'], $at['lng'], (float) $store->latitude, (float) $store->longitude) : 50.0;
            $score = $km + $out * 2.0; // each delivery already out counts as 2 km
            if (! $best || $score < $best[1]) {
                $best = [$rider, $score];
            }
        }

        return $best[0] ?? null;
    }

    /** Hours the seller's riders get to take an offered order (seller sets 1–72; default 5). */
    public static function pickupHours(\App\Models\Store $store): int
    {
        return max(1, min(72, (int) ($store->rider_pickup_hours ?? 5)));
    }

    /**
     * Send the shop's part of a local order out now, with a rider (or null = the seller delivers it):
     * package, delivery code, buyer told. Any open offer to the riders ends.
     */
    public static function dispatchLocal(\App\Models\Order $order, Shop $shop, ?User $rider): OrderPackage
    {
        $promise = $order->shopShipping()->where('shop_id', $shop->id)->first();
        abort_unless($promise?->method === 'local', 422, 'This order wasn\'t placed for local delivery — ship it with a courier.');
        $items = SellerFulfillment::shopLines($order, $shop)
            ->map(fn ($l) => ['order_item_id' => $l->id, 'quantity' => SellerFulfillment::remainingQuantity($l) - SellerFulfillment::requestedQuantity($l)])
            ->filter(fn ($i) => $i['quantity'] > 0)->values()->all();
        abort_if($items === [], 422, 'Everything on this order has already gone out.');
        $addressId = (int) ($shop->local_delivery['address_id'] ?? 0) ?: $shop->addresses()->orderByDesc('is_default')->value('id');
        $package = SellerFulfillment::createPackage($order, $shop, $items, (int) $addressId, [
            'label_source' => 'local',
            'carrier' => SellerShipping::LOCAL,
            'tracking_number' => sprintf('NT-%d-L%d', $order->id, $order->packages()->count() + 1),
            'delivery_code' => (string) random_int(1000, 9999),
        ]);
        SellerProgress::advance($package, 'out_for_delivery', 'seller');
        self::assign($package, $rider);
        $promise->forceFill(['rider_offer_until' => null, 'rider_offer_missed_at' => null])->save();

        return $package;
    }

    /**
     * Offer a local order to all the seller's riders: every rider at the store sees it with one
     * deadline, and the first to take it gets it. The buyer is told only when a rider takes it.
     */
    public static function offer(\App\Models\Order $order, Shop $shop): void
    {
        $promise = $order->shopShipping()->where('shop_id', $shop->id)->first();
        abort_unless($promise?->method === 'local', 422, 'This order wasn\'t placed for local delivery — ship it with a courier.');
        $store = SellerStores::ensure($shop);
        $until = now()->addHours(self::pickupHours($store));
        $promise->forceFill(['rider_offer_until' => $until, 'rider_offer_missed_at' => null])->save();
        $online = $store->riders()->where('is_rider', true)->where('rider_is_active', true)->where('rider_available', true)->get()
            ->filter(fn (User $r) => $r->currentShift() && ! SellerRiderCash::blockedReason($r));
        foreach ($online as $rider) {
            try {
                $rider->notify(new RiderNotice("Delivery to take — {$shop->name}", "{$shop->name} has order #{$order->id} for its riders until {$until->format('j M H:i')}. The first to press Take it in the Rider app (Store deliveries) gets it."));
            } catch (\Throwable $e) {
                report($e);
            }
        }
        if ($online->isEmpty() && $shop->seller) {
            SellerNotify::send($shop->seller, $shop->seller->user, 'No rider online', "None of your riders is on shift right now. Order #{$order->id} stays open to them until {$until->format('j M H:i')} — call a rider, or deliver it yourself.");
        }
    }

    /** Orders offered to a rider's stores (still open, incl. past the deadline until someone sends them out). */
    public static function openOffers(User $rider)
    {
        $shopIds = $rider->stores()->whereNotNull('shop_id')->where('local_delivery_active', true)->pluck('shop_id');

        return \App\Models\OrderShopShipping::query()->whereIn('shop_id', $shopIds)->whereNotNull('rider_offer_until')
            ->with(['order.items', 'shop:id,name'])->oldest('rider_offer_until')->get();
    }

    /** A rider takes an offered order: first come, first served (locked). */
    public static function take(User $rider, \App\Models\OrderShopShipping $promise): OrderPackage
    {
        abort_unless($rider->stores()->where('shop_id', $promise->shop_id)->exists(), 404);
        abort_if(SellerRiderCash::blockedReason($rider) || SellerRiderCash::paused($rider, SellerStores::ensure($promise->shop)), 422, 'You’re paused for cash — hand it over before taking more deliveries.');

        return \Illuminate\Support\Facades\DB::transaction(function () use ($rider, $promise) {
            $locked = \App\Models\OrderShopShipping::whereKey($promise->id)->lockForUpdate()->first();
            abort_unless($locked?->rider_offer_until, 422, 'Another rider has already taken this order.');

            return self::dispatchLocal($locked->order, $locked->shop, $rider);
        });
    }

    /** Every few minutes: offers past their deadline with nobody → the seller is told (once); riders can still take them. */
    public static function sweepOffers(): int
    {
        $missed = \App\Models\OrderShopShipping::query()->whereNotNull('rider_offer_until')->whereNull('rider_offer_missed_at')
            ->where('rider_offer_until', '<=', now())->with('shop.seller')->get();
        foreach ($missed as $promise) {
            $promise->forceFill(['rider_offer_missed_at' => now()])->save();
            if ($promise->shop?->seller) {
                SellerNotify::send($promise->shop->seller, $promise->shop->seller->user, 'Nobody took a delivery', "No rider took order #{$promise->order_id} in time. Deliver it yourself, give it to a rider, or send it by courier (Manage orders → Orders you ship). It stays open to your riders meanwhile.");
            }
        }

        return $missed->count();
    }

    /** Give a package to a rider (or null = the seller delivers it), telling the rider. */
    public static function assign(OrderPackage $package, ?User $rider): void
    {
        $package->forceFill(['rider_id' => $rider?->id, 'rider_assigned_at' => $rider ? now() : null])->save();
        if ($rider) {
            $shop = $package->shop?->name ?? 'A store';
            try {
                $rider->notify(new RiderNotice("New delivery from {$shop}", "{$shop} gave you order #{$package->order_id} to deliver. Open the Rider app for the address; ask the buyer for their delivery code at the door."));
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }

    /** A local (own-delivery) order worth more than cash on delivery allows: send it by courier or deliver it yourself. */
    public static function highValue(\App\Models\Order $order, Shop $shop): bool
    {
        $method = $order->shopShipping->firstWhere('shop_id', $shop->id)?->method;

        return $method === 'local' && (int) $order->total_cents > SellerRiderCash::codMaxCents($order->market, $shop);
    }

    /** Cash to collect for a package on a cash-on-delivery order (in the order's currency). */
    public static function cashCents(OrderPackage $package): int
    {
        if ($package->order?->payment_method !== 'cod') {
            return 0;
        }

        // The same amount the seller confirms (a seller's cash-on-delivery order is theirs alone).
        return (int) $package->order->total_cents;
    }

    /** What the rider app shows for a package. Buyer details only while it's theirs to deliver. */
    public static function forRider(OrderPackage $package): array
    {
        $order = $package->order;
        $address = (array) $order?->delivery_address;
        $from = $package->shop?->addresses()->find($package->ship_from_address_id);

        return [
            'id' => $package->id,
            'order_id' => $package->order_id,
            'status' => $package->status,
            'shop' => $package->shop?->name,
            'pickup' => $from ? implode(', ', array_filter([$from->line1, $from->city, $from->postal_code])) : null,
            'buyer' => $address['name'] ?? $order?->user?->name,
            'phone' => $address['phone'] ?? $order?->user?->phone,
            'address' => implode(', ', array_filter([$address['line1'] ?? null, $address['line2'] ?? null, $address['city'] ?? null, $address['postal_code'] ?? null])),
            'instructions' => $order?->delivery_instructions,
            'items' => $package->items->map(fn ($i) => ($i->orderItem?->product_name ?? 'Item').' × '.$i->quantity)->values(),
            'cash_cents' => self::cashCents($package),
            'currency' => Market::currency($order?->market),
            'assigned_at' => $package->rider_assigned_at,
        ];
    }
}
