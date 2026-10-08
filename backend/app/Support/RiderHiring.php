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
