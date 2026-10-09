<?php

namespace App\Support;

use App\Models\Shop;
use App\Models\Store;
use App\Models\User;
use App\Notifications\AdminLocalDeliveryOff;
use App\Notifications\RiderNotice;
use Illuminate\Support\Facades\Notification;

/**
 * A seller's store in Stores / hubs: the base their own local delivery runs from
 * (and their riders). Every seller shop has one, with local delivery off until
 * switched on. It mirrors the seller's Own delivery (local) settings — address,
 * map point and radius — so there's one place to set them.
 *
 * stores.is_active             local delivery running (seller's settings are on)
 * stores.local_delivery_active allowed: false = locked, only admin can turn it on again
 * stores.local_delivery_off_requested_at  the seller asked to turn it off; admin decides
 */
class SellerStores
{
    /** The shop's store, created (local delivery off) if it has none yet. */
    public static function ensure(Shop $shop): Store
    {
        $store = Store::query()->where('shop_id', $shop->id)->first();
        if ($store) {
            return $store;
        }
        $address = $shop->addresses()->orderByDesc('is_default')->orderBy('id')->first();
        $seller = $shop->seller;
        $store = new Store;
        $store->forceFill([
            'shop_id' => $shop->id,
            'name' => $shop->name,
            'line1' => $address?->line1 ?? $seller?->registered_line1 ?? '',
            'line2' => $address?->line2 ?? $seller?->registered_line2,
            'city' => $address?->city ?? $seller?->registered_city ?? '',
            'state' => mb_substr((string) ($address?->state ?? $seller?->registered_state ?? ''), 0, 60),
            'postal_code' => mb_substr((string) ($address?->postal_code ?? $seller?->registered_postal_code ?? ''), 0, 12),
            'country' => $shop->market,
            'delivery_radius_km' => (int) ceil(min(10, SellerShipping::localMaxKm())),
            'is_active' => false,
        ])->save();

        return $store;
    }

    /** Create or update the shop's store from its own-delivery settings. */
    public static function sync(Shop $shop): Store
    {
        $local = (array) $shop->local_delivery;
        $store = self::ensure($shop);
        $on = ! empty($local['radius_km']) && isset($local['lat'], $local['lng']) && $shop->shipsItself();
        if (! $on) {
            $store->forceFill(['is_active' => false, 'name' => $shop->name])->save();

            return $store;
        }
        $address = $shop->addresses()->find($local['address_id'] ?? 0);
        $store->forceFill([
            'name' => $shop->name,
            'line1' => $address?->line1 ?? $store->line1,
            'line2' => $address?->line2 ?? $store->line2,
            'city' => $address?->city ?? $store->city,
            'state' => $address?->state ?? $store->state,
            'postal_code' => $address?->postal_code ?? $store->postal_code,
            'country' => $shop->market,
            'latitude' => (float) $local['lat'],
            'longitude' => (float) $local['lng'],
            'delivery_radius_km' => max(1, (int) ceil((float) $local['radius_km'])),
            'is_active' => true,
        ])->save();

        return $store;
    }

    /** Locked: only admin can turn local delivery on (after it was turned off). */
    public static function blockedByAdmin(Shop $shop): bool
    {
        return Store::query()->where('shop_id', $shop->id)->where('local_delivery_active', false)->exists();
    }

    /** off | on | off_requested | locked */
    public static function status(Store $store): string
    {
        return match (true) {
            ! $store->local_delivery_active => 'locked',
            $store->local_delivery_off_requested_at !== null => 'off_requested',
            $store->is_active => 'on',
            default => 'off',
        };
    }

    /**
     * The seller turns local delivery off. With riders linked it waits for the
     * store's admin (riders hear nothing yet) and, once approved, is locked: only
     * the store can turn it on again. With no riders it's simply off, and the
     * seller can turn it on again any time. Returns the new status.
     */
    public static function sellerTurnsOff(Shop $shop, User $by): string
    {
        $store = self::ensure($shop);
        $riders = $store->riders()->count();
        if ($riders > 0) {
            $store->forceFill(['local_delivery_off_requested_at' => now()])->save();
            try {
                Notification::send(User::where('is_admin', true)->get(), new AdminLocalDeliveryOff($store->load('shop'), $riders));
            } catch (\Throwable $e) {
                report($e);
            }

            return 'off_requested';
        }
        $shop->forceFill(['local_delivery' => null])->save();

        return 'off';
    }

    /** Admin approves the seller's request (or turns it off themselves): off, locked, riders told and unlinked. */
    public static function finishOff(Store $store, User $by, bool $notifySeller = true, ?string $reason = null): void
    {
        $shop = $store->shop;
        $riders = $store->riders()->get();
        $store->forceFill(['local_delivery_active' => false, 'local_delivery_off_requested_at' => null])->save();
        if ($shop && $shop->local_delivery) {
            $shop->forceFill(['local_delivery' => null])->save(); // the store goes inactive with it
        }
        $name = $shop?->name ?? $store->name;
        foreach ($riders as $rider) {
            // No other store delivering now: their details stay on file; they're linked again when a store near home starts.
            $none = RiderHiring::activeStores($rider)->isEmpty();
            try {
                $rider->notify(new RiderNotice("{$name} has ended local delivery",
                    "{$name} has ended its local delivery, so you won't get its orders any more. Delivery fees you earned there are paid at the end of the month to the payout method in your Rider account.".($none
                        ? ' You have no other store right now. Your details stay on file, and a store near your home that starts deliveries may invite you (you choose whether to join). Moving? Apply to a store near your new home. Or delete your details in the Rider app once your pay is settled.'
                        : ' Your other stores aren’t affected.')));
            } catch (\Throwable $e) {
                report($e);
            }
        }
        // Riders stay linked (their pay for this store is still due); a store that's off sends them no orders.
        if ($notifySeller && $shop?->seller) {
            SellerNotify::send($shop->seller, $by, 'Local delivery turned off',
                'Your own local delivery is now off'.($reason ? ": {$reason}" : '.').' Orders go by courier. '.($riders->count() ? "The {$riders->count()} rider".($riders->count() === 1 ? '' : 's').' linked to your store were told; their pay for deliveries already made is still due. ' : '').'To turn it back on later, message us.');
        }
    }

    /** Admin keeps local delivery on after the seller asked to turn it off. */
    public static function keepOn(Store $store, User $by, string $reason): void
    {
        $store->forceFill(['local_delivery_off_requested_at' => null])->save();
        if ($seller = $store->shop?->seller) {
            SellerNotify::send($seller, $by, 'Local delivery stays on', "Your request to turn off local delivery wasn't approved: {$reason} Reply here if you'd like to talk it through.");
        }
    }

    /**
     * Admin turns local delivery on for a seller's store (also unlocking it). If the
     * seller never set own delivery up, it starts from the store: its address, map
     * point and radius, free, delivered within a day — the seller can change the fee and days.
     */
    public static function adminTurnsOn(Store $store, User $by): void
    {
        $shop = $store->shop;
        abort_unless($shop, 422, 'Only sellers’ stores have this switch.');
        abort_unless($shop->shipsItself(), 422, 'This seller doesn’t ship orders themselves, so local delivery can’t run for them.');
        $store->forceFill(['local_delivery_active' => true, 'local_delivery_off_requested_at' => null])->save();
        if (! $shop->local_delivery) {
            abort_unless($store->hasCoordinates(), 422, 'Set the store’s location first (Edit → address or map pin).');
            $address = $shop->addresses()->orderByDesc('is_default')->orderBy('id')->first();
            abort_unless($address, 422, 'The seller has no ship-from address yet.');
            $shop->forceFill(['local_delivery' => [
                'address_id' => $address->id, 'radius_km' => (float) $store->delivery_radius_km, 'fee_cents' => 0, 'days' => 1,
                'lat' => $store->latitude, 'lng' => $store->longitude,
            ]])->save();
        }
        RiderHiring::relinkNearby($store->fresh()); // riders near it with no active store are suggested to it
        if ($seller = $shop->seller) {
            SellerNotify::send($seller, $by, 'Local delivery is on', 'Your local delivery is on — only buyers within '.$store->delivery_radius_km.' km of your store get it (you or your riders deliver); everyone else still gets courier shipping. Check the fee and delivery days in Shipping settings → Local delivery.');
        }
    }
}
