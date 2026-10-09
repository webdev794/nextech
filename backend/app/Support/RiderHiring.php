<?php

namespace App\Support;

use App\Models\Order;
use App\Models\RiderApplication;
use App\Models\Setting;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Hiring riders for a store — NexTech's own or a seller's. A store that's
 * "hiring" appears on the rider application page and turns on the storefront's
 * "Work with us" link for signed-in buyers. Applications to a seller's store are
 * decided by that seller (the store's admin can decide any). Riders must be at
 * least the country's minimum age and bring their own vehicle.
 */
class RiderHiring
{
    /**
     * The store's terms a rider signs to join (shown at the end of the application, saved with the signature).
     *
     * @return list<string>
     */
    public static function terms(?Store $store): array
    {
        $brand = Branding::name();
        $hours = $store ? RiderWorkHours::label(RiderWorkHours::of($store)) : null;
        $off = $store ? RiderWorkHours::daysOffAllowed($store) : 4;

        return [
            'Be polite and well-behaved with buyers, sellers and the store’s team; dress neatly.',
            'Be on time: clock in for the store’s working hours'.($hours ? " ({$hours})" : '').' and deliver orders on time.',
            "Days off: up to {$off} a month, asked for in the Rider app at least one day ahead. Missing a working day without asking may affect that day’s pay or this agreement.",
            'Fuel, your vehicle’s upkeep, repairs, phone and mobile data are at your own cost.',
            'Look after the products you carry; hand over any cash you collect the same day.',
            'Very good work — 5-star ratings on a high number of deliveries — can earn a bonus.',
            "Follow the rules the store and the seller set; {$brand}’s decision is final on pay, hours, days off, bonuses and other matters.",
            'Give at least 30 days’ notice before you stop working.',
        ];
    }

    /** The stores a rider works for that are delivering now (own store with riders on, or a seller store that's on). */
    public static function activeStores(User $rider)
    {
        return $rider->stores()->with('shop')->get()->filter(fn (Store $s) => $s->shop_id
            ? SellerStores::status($s) === 'on'
            : ($s->is_active && $s->local_delivery_active))->values();
    }

    /**
     * A store starts riders / local delivery: riders with no active store whose home
     * is inside its area (same country) are suggested to the store — not linked. The
     * store invites the ones it wants; a rider joins only if they accept.
     */
    public static function relinkNearby(Store $store): int
    {
        if (! $store->hasCoordinates()) {
            return 0;
        }
        $found = [];
        $riders = User::query()->where('is_rider', true)->where('rider_is_active', true)->whereNotNull('rider_base_lat')->whereNotNull('rider_base_lng')
            ->whereDoesntHave('stores', fn ($q) => $q->whereKey($store->id))->get();
        foreach ($riders as $rider) {
            if (self::activeStores($rider)->isNotEmpty() || $rider->stores()->where('country', '!=', $store->country)->exists()) {
                continue; // working elsewhere, or another country
            }
            if (Geo::haversineKm((float) $rider->rider_base_lat, (float) $rider->rider_base_lng, (float) $store->latitude, (float) $store->longitude) > (float) $store->delivery_radius_km) {
                continue;
            }
            $invite = \App\Models\RiderInvite::firstOrCreate(['store_id' => $store->id, 'user_id' => $rider->id], ['status' => 'suggested']);
            if ($invite->wasRecentlyCreated) {
                $found[] = $rider->name;
            }
        }
        if ($found) {
            RiderWorkHours::notifyStore($store->loadMissing('shop.seller'), 'Riders near you who delivered before',
                count($found).' rider'.(count($found) === 1 ? '' : 's').' who delivered before live'.(count($found) === 1 ? 's' : '').' near your store ('.implode(', ', $found).'). Invite them if you’d like — they join only if they accept. '.($store->shop_id ? 'See Local delivery & riders.' : 'See Riders.'));
        }

        return count($found);
    }

    /** The store's answer to a suggested rider: invite (the rider is asked) or no thanks. */
    public static function storeDecides(\App\Models\RiderInvite $invite, bool $invite_): void
    {
        abort_unless($invite->status === 'suggested', 422, 'Already answered.');
        $invite->update(['status' => $invite_ ? 'invited' : 'declined']);
        if ($invite_) {
            $name = $invite->store->shop?->name ?? $invite->store->name;
            try {
                $invite->user->notify(new \App\Notifications\RiderNotice("{$name} invites you to deliver", "{$name}, near your home, would like you to deliver for them again. Open the Rider app to join or say no thanks."));
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }

    /** The rider's answer to an invitation: join (linked now) or no thanks (e.g. working elsewhere). */
    public static function riderDecides(\App\Models\RiderInvite $invite, bool $join): void
    {
        abort_unless($invite->status === 'invited', 422, 'This invitation isn’t open.');
        $store = $invite->store;
        if ($join) {
            self::oneCountry($invite->user, [$store->id]);
            $invite->user->stores()->syncWithoutDetaching([$store->id]);
        }
        $invite->update(['status' => $join ? 'accepted' : 'refused']);
        RiderWorkHours::notifyStore($store->loadMissing('shop.seller'), $join ? 'Rider joined' : 'Rider said no thanks',
            $invite->user->name.($join ? ' accepted your invitation and is linked to your store.' : ' said no thanks to your invitation.'));
    }

    /** A rider asks to move to a new home area (at their own expense): the store decides. */
    public static function requestMove(User $rider, string $address): void
    {
        [$lat, $lng] = Geo::geocode($address);
        abort_if($lat === null, 422, 'We couldn’t find that address on the map — check it and try again.');
        $rider->forceFill(['rider_move_request' => ['address' => $address, 'lat' => $lat, 'lng' => $lng, 'at' => now()->toIso8601String()]])->save();
        try {
            \Illuminate\Support\Facades\Notification::send(User::where('is_admin', true)->get(), new \App\Notifications\AdminNotice("{$rider->name} asks to move", "{$rider->name} wants to move to: {$address}. Approve or decline in Riders → {$rider->name}."));
        } catch (\Throwable $e) {
            report($e);
        }
        foreach ($rider->stores()->whereNotNull('shop_id')->with('shop.seller')->get() as $store) {
            if ($store->shop?->seller) {
                SellerNotify::send($store->shop->seller, $store->shop->seller->user, 'A rider may move away', "{$rider->name} asked to move to another area. If ".Branding::name().' approves, they stop delivering for you; pay already earned is still due.');
            }
        }
    }

    /**
     * The store decides a move: approved → new home, unlinked from old stores (pay still due),
     * stores near the new home are suggested (they invite; the rider accepts). Declined → told why.
     */
    public static function decideMove(User $rider, bool $approve, ?string $reason = null): void
    {
        $move = $rider->rider_move_request;
        abort_unless($move, 422, 'There’s no move request.');
        if ($approve) {
            abort_if(\App\Models\Order::query()->where('delivery_partner_id', $rider->id)->whereIn('status', ['ready_for_delivery', 'out_for_delivery'])->exists()
                || \App\Models\OrderPackage::query()->where('rider_id', $rider->id)->whereNotIn('status', ['delivered', 'returned', 'lost'])->exists(), 422, 'They still have deliveries out — finish or reassign them first.');
            abort_if(RiderMoney::cashHeldCents($rider) > 0, 422, 'They still hold cash — it must be handed over first.');
            $rider->stores()->detach();
            $rider->forceFill(['rider_base_lat' => $move['lat'], 'rider_base_lng' => $move['lng'], 'rider_move_request' => null])->save();
            $found = 0;
            foreach (Store::query()->where('is_active', true)->get() as $store) {
                if ($store->hasCoordinates() && Geo::haversineKm((float) $move['lat'], (float) $move['lng'], (float) $store->latitude, (float) $store->longitude) <= (float) $store->delivery_radius_km) {
                    $invite = \App\Models\RiderInvite::firstOrCreate(['store_id' => $store->id, 'user_id' => $rider->id], ['status' => 'suggested']);
                    if ($invite->wasRecentlyCreated) {
                        RiderWorkHours::notifyStore($store->loadMissing('shop.seller'), 'A rider moved near you', "{$rider->name}, who delivered before, has moved near your store. Invite them if you’d like — they join only if they accept.");
                        $found++;
                    }
                }
            }
            $text = 'Your move is approved. You’re no longer linked to your old stores (pay you earned is still paid). '.($found ? 'Stores near your new home can now invite you — you choose whether to join.' : 'No store near your new home has deliveries yet; you’ll be told when one invites you, or you can apply.');
        } else {
            $rider->forceFill(['rider_move_request' => null])->save();
            $text = 'Your move wasn’t approved'.($reason ? ": {$reason}" : '.').' You keep working for your stores as before.';
        }
        try {
            $rider->notify(new \App\Notifications\RiderNotice($approve ? 'Move approved' : 'Move not approved', $text));
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /** A rider deletes their details (no active store, nothing owed either way): documents and personal data removed. */
    public static function deleteDetails(User $rider): void
    {
        abort_if(self::activeStores($rider)->isNotEmpty(), 422, 'You still work for a store — your details are needed while you do.');
        abort_if(RiderLedger::balanceCents($rider) !== 0, 422, 'Your pay must be settled first (your balance isn’t zero).');
        abort_if(RiderMoney::cashHeldCents($rider) > 0, 422, 'Hand over the cash you hold first.');
        foreach (RiderApplication::query()->where('user_id', $rider->id)->get() as $app) {
            foreach (['id_document_path', 'education_document_path', 'photo_path', 'license_document_path', 'rc_document_path'] as $field) {
                if ($app->{$field}) {
                    \Illuminate\Support\Facades\Storage::disk('local')->delete($app->{$field});
                }
            }
            $app->delete();
        }
        $rider->stores()->detach();
        $rider->forceFill([
            'is_rider' => false, 'rider_is_active' => false, 'rider_available' => false,
            'rider_payout_method' => null, 'rider_payout_details' => null,
            'rider_base_lat' => null, 'rider_base_lng' => null,
        ])->save();
    }

    /** A rider delivers in one country only (local riders): every store they're linked to must be in it. */
    public static function oneCountry(User $rider, array $storeIds): void
    {
        $countries = Store::query()->whereIn('id', array_merge($storeIds, $rider->stores()->pluck('stores.id')->all()))->distinct()->pluck('country');
        abort_if($countries->count() > 1, 422, 'A rider works in one country only — these stores are in '.$countries->join(' and ').'.');
    }

    /** Store ids an application asked for, in the applicant's order of priority. */
    public static function preferred(RiderApplication $application): array
    {
        return array_values(array_unique(array_map('intval', $application->preferred_store_ids ?: array_filter([$application->store_id]))));
    }

    /** Stores taking rider applications right now. */
    public static function hiringStores(): Builder
    {
        return Store::query()->where('hiring_open', true)->where(fn ($q) => $q
            // NexTech's own store: active.
            ->where(fn ($own) => $own->whereNull('shop_id')->where('is_active', true))
            // A seller's store: local delivery on and allowed, seller approved.
            ->orWhere(fn ($seller) => $seller->whereNotNull('shop_id')->where('is_active', true)->where('local_delivery_active', true)
                ->whereHas('shop.seller', fn ($s) => $s->where('status', 'approved'))));
    }

    /** Minimum age for riders in a country (admin-set per country; 18 by default). */
    public static function minAge(?string $country): int
    {
        $saved = (array) Setting::get('rider_min_age', []);

        return max(16, min(30, (int) ($saved[strtoupper((string) $country)] ?? 18)));
    }

    /** Orders this rider still has from a store (assigned, not yet delivered or cancelled). */
    public static function openOrders(User $rider, Store $store): int
    {
        return Order::query()->where('delivery_partner_id', $rider->id)->where('store_id', $store->id)
            ->whereIn('status', ['ready_for_delivery', 'out_for_delivery'])->count();
    }

    /**
     * What an applicant is missing for the country's minimum rules: age, ID proof,
     * photo, home location, licence and vehicle RC for motor vehicles, consent.
     * Seller or admin can only accept when this is empty.
     *
     * @return list<string>
     */
    public static function unmet(RiderApplication $application): array
    {
        $min = self::minAge($application->store?->country);
        $motor = $application->vehicle_type !== 'bicycle';

        return array_values(array_filter([
            ! $application->date_of_birth ? 'date of birth' : ($application->date_of_birth->age < $min ? "at least {$min} years old (is {$application->date_of_birth->age})" : null),
            ! $application->id_document_path ? 'ID proof' : null,
            ! $application->photo_path ? 'photo' : null,
            $application->home_lat === null || $application->home_lng === null ? 'home location on the map' : null,
            $motor && ! $application->license_document_path ? 'driving licence' : null,
            $motor && ! $application->rc_document_path ? 'vehicle RC' : null,
            ! $application->own_vehicle ? 'own vehicle' : null,
            ! $application->consent_removal ? 'consent to the removal terms' : null,
        ]));
    }

    /** Hire an applicant at their chosen store (and any extra stores admin picks). */
    public static function hire(RiderApplication $application, User $by, array $storeIds = [], bool $bySeller = false): void
    {
        $user = $application->user;
        abort_if($user->is_admin, 422, 'That account is an administrator.');
        $missing = self::unmet($application);
        abort_if($missing !== [], 422, 'Can’t accept yet — the local rules need: '.implode(', ', $missing).'.');
        // Their rider profile comes from the application: base location, photo and the application itself.
        $user->forceFill([
            'is_rider' => true,
            'rider_is_active' => true,
            'rider_since' => $user->rider_since ?? now(),
            'phone' => $user->phone ?: $application->phone,
            'rider_base_address' => $application->home_address,
            'rider_base_lat' => $application->home_lat,
            'rider_base_lng' => $application->home_lng,
            'rider_photo_path' => $application->photo_path,
            'rider_application_id' => $application->id,
            // How they asked to be paid (they can change it later in Earnings).
            'rider_payout_method' => $user->rider_payout_method ?: $application->payout_method,
            'rider_payout_details' => $user->rider_payout_method ? $user->rider_payout_details : $application->payout_details,
        ])->save();
        $storeIds = $storeIds ?: array_filter([$application->store_id]);
        abort_if($storeIds === [], 422, 'Pick a store for this rider.');
        self::oneCountry($user, $storeIds);
        $user->stores()->syncWithoutDetaching($storeIds);
        $application->update([
            'status' => 'approved', 'rejection_reason' => null,
            'reviewed_by' => $by->id, 'reviewed_at' => now(), 'decided_by_seller' => $bySeller,
        ]);
    }
}
