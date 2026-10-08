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
