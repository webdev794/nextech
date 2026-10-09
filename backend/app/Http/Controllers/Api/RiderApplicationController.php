<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RiderApplication;
use App\Models\Store;
use App\Support\Geo;
use App\Support\RiderHiring;
use App\Support\VisitorCountry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Applying to become a delivery rider. Any signed-in user may apply (gated by
 * auth:sanctum only, since an applicant is not a rider yet); an admin approves
 * or rejects it from the Riders tab (AdminRiderApplicationController).
 */
class RiderApplicationController extends Controller
{
    public const VEHICLES = ['bicycle', 'scooter', 'motorbike', 'car'];

    /** The caller's application (or null) plus whether they're already a rider. */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json(['data' => [
            'is_rider' => (bool) $user->is_rider,
            'application' => RiderApplication::with('store:id,name,city')->where('user_id', $user->id)->first(),
        ]]);
    }

    /**
     * Active stores, nearest first when a location (or an address to
     * geocode) is given, each with its current rider count so an applicant
     * can see which nearby store is short of riders.
     */
    public function stores(Request $request): JsonResponse
    {
        $data = $request->validate([
            'lat' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'lng' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
            'address' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        $lat = isset($data['lat']) ? (float) $data['lat'] : null;
        $lng = isset($data['lng']) ? (float) $data['lng'] : null;
        if (($lat === null || $lng === null) && ! empty($data['address'])) {
            [$lat, $lng] = Geo::geocode($data['address']);
        }

        // No location yet: the visitor's own country (from their IP) first.
        $country = ($lat === null || $lng === null) ? VisitorCountry::detect($request) : null;

        // Only stores that are hiring: NexTech's own and sellers' (local delivery on).
        $stores = RiderHiring::hiringStores()->with('shop:id,name')
            ->withCount(['riders' => fn ($q) => $q->where('is_rider', true)])
            ->when($country, fn ($q) => $q->orderByRaw('country = ? DESC', [$country]))
            ->get(['id', 'shop_id', 'name', 'line1', 'city', 'state', 'postal_code', 'country', 'latitude', 'longitude', 'delivery_radius_km'])
            ->map(function (Store $store) use ($lat, $lng) {
                $km = ($lat !== null && $lng !== null && $store->latitude !== null && $store->longitude !== null)
                    ? Geo::haversineKm($lat, $lng, (float) $store->latitude, (float) $store->longitude)
                    : null;

                return [
                    'id' => $store->id,
                    'name' => $store->name,
                    'address' => implode(', ', array_filter([$store->line1, $store->city, trim($store->state.' '.$store->postal_code)])),
                    'riders_count' => (int) $store->riders_count,
                    // Who the rider would deliver for: the store itself, or the seller who runs it.
                    'seller' => $store->shop?->name,
                    'country' => $store->country,
                    'min_age' => RiderHiring::minAge($store->country),
                    'distance_miles' => $km !== null ? round($km / 1.609344, 1) : null,
                    // The store's terms the rider signs: working hours and days off a month.
                    'hours' => \App\Support\RiderWorkHours::label(\App\Support\RiderWorkHours::of($store)),
                    'days_off_per_month' => \App\Support\RiderWorkHours::daysOffAllowed($store),
                    'terms' => RiderHiring::terms($store),
                    // How riders may be paid in this country (admin's allowed methods).
                    'payout_methods' => array_values(array_filter(['bank', 'paypal'], fn ($m) => (bool) (\App\Support\SellerPayouts::fees($store->country)[$m]['enabled'] ?? false))) ?: ['bank'],
                ];
            })
            ->sortBy(fn ($s) => $s['distance_miles'] ?? PHP_FLOAT_MAX)
            ->values();

        return response()->json(['data' => [
            'stores' => $stores,
            'located' => $lat !== null && $lng !== null ? ['lat' => $lat, 'lng' => $lng] : null,
        ]]);
    }

    /** Whether any store is hiring in the visitor's country — turns on the storefront's "Work with us" link. */
    public function hiring(Request $request): JsonResponse
    {
        $country = VisitorCountry::detect($request);
        $count = RiderHiring::hiringStores()->when($country, fn ($q) => $q->where('country', $country))->count();

        return response()->json(['data' => ['open' => $count > 0, 'stores' => $count]]);
    }

    /** Submit (or, after a rejection, resubmit) an application. */
    public function apply(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_if($user->is_rider, 422, 'You are already a rider.');
        abort_if($user->is_admin, 422, 'Administrator accounts cannot apply as riders.');

        $data = $request->validate([
            // The stores they're willing to work for, most wanted first.
            'store_ids' => ['required', 'array', 'min:1', 'max:10'],
            'store_ids.*' => ['integer', 'distinct', Rule::in(RiderHiring::hiringStores()->pluck('id')->all())],
            'phone' => ['required', 'string', 'max:40'],
            'email' => ['required', 'email', 'max:160'],
            'date_of_birth' => ['required', 'date', 'before:today'],
            // Riders bring their own vehicle and pay its running costs.
            'own_vehicle' => ['accepted'],
            // Delivery / driving work experience in months (0 = none).
            'experience_months' => ['required', 'integer', 'min:0', 'max:600'],
            'education' => ['required', 'string', 'max:160'],
            'work_history' => ['nullable', 'string', 'max:2000'],
            'health_issue' => ['required', 'boolean'],
            'health_details' => ['required_if_accepted:health_issue', 'nullable', 'string', 'max:300'],
            // "The seller or the store can remove me at any time if stores aren't available, or for behaviour, health or other issues",
            // and "I'll give 30 days' notice before leaving; without notice, final pay is settled after checks".
            'consent_removal' => ['accepted'],
            // Signed at the end: their name and the place they are now, after reading the store's terms.
            'signed_name' => ['required', 'string', 'min:2', 'max:120'],
            'signed_place' => ['required', 'string', 'min:2', 'max:120'],
            // How they want to be paid (so they can start on day one).
            'payout_method' => ['required', Rule::in(['bank', 'paypal'])],
            'holder_name' => ['required_if:payout_method,bank', 'nullable', 'string', 'max:160'],
            'account_number' => ['required_if:payout_method,bank', 'nullable', 'string', 'max:60'],
            'routing_number' => ['required_if:payout_method,bank', 'nullable', 'string', 'max:60'],
            'bank_name' => ['required_if:payout_method,bank', 'nullable', 'string', 'max:160'],
            'payout_email' => ['required_if:payout_method,paypal', 'nullable', 'email', 'max:160'],
            'rc_document_path' => ['required_unless:vehicle_type,bicycle', 'nullable', 'string', 'max:255', 'starts_with:kyc/'.$user->id.'/'],
            // ID proof is required; an education document and a photo are optional (private uploads).
            'id_document_path' => ['required', 'string', 'max:255', 'starts_with:kyc/'.$user->id.'/'],
            'education_document_path' => ['nullable', 'string', 'max:255', 'starts_with:kyc/'.$user->id.'/'],
            'photo_path' => ['required', 'string', 'max:255', 'starts_with:kyc/'.$user->id.'/'],
            'home_address' => ['required', 'string', 'max:255'],
            'home_lat' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'home_lng' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
            'vehicle_type' => ['required', Rule::in(self::VEHICLES)],
            'license_number' => ['required_unless:vehicle_type,bicycle', 'nullable', 'string', 'max:60'],
            // From POST /seller/kyc-document (kind=license_document) — must be
            // the caller's own private upload.
            'license_document_path' => ['required_unless:vehicle_type,bicycle', 'nullable', 'string', 'max:255', 'starts_with:kyc/'.$user->id.'/'],
        ]);

        // Minimum age in the store's country (checked against the ID when the application is decided).
        $data['preferred_store_ids'] = array_values(array_map('intval', $data['store_ids']));
        $data['store_id'] = $data['preferred_store_ids'][0];
        unset($data['store_ids']);
        $data['consent_removal'] = true;
        $data['signed_at'] = now();
        $store = Store::find($data['store_id']);
        $data['signed_terms'] = RiderHiring::terms($store); // exactly what they signed, kept with the application
        $allowed = array_values(array_filter(['bank', 'paypal'], fn ($m) => (bool) (\App\Support\SellerPayouts::fees($store?->country)[$m]['enabled'] ?? false))) ?: ['bank'];
        abort_unless(in_array($data['payout_method'], $allowed, true), 422, 'Choose how to be paid from: '.implode(' or ', array_map(fn ($m) => \App\Support\SellerPayouts::label($m), $allowed)).'.');
        $data['payout_details'] = $data['payout_method'] === 'bank'
            ? array_intersect_key($data, array_flip(['holder_name', 'account_number', 'routing_number', 'bank_name']))
            : ['email' => $data['payout_email']];
        unset($data['holder_name'], $data['account_number'], $data['routing_number'], $data['bank_name'], $data['payout_email']);
        $min = RiderHiring::minAge($store?->country);
        abort_if(\Illuminate\Support\Carbon::parse($data['date_of_birth'])->age < $min, 422, "Riders must be at least {$min} years old.");
        $data['own_vehicle'] = true;

        if (empty($data['home_lat']) || empty($data['home_lng'])) {
            [$data['home_lat'], $data['home_lng']] = Geo::geocode($data['home_address']);
        }
        // Their base location is needed to offer nearby work.
        abort_if($data['home_lat'] === null || $data['home_lng'] === null, 422, 'We couldn’t find your home address on the map — press “Use my location” or check the address.');

        $application = DB::transaction(function () use ($user, $data): RiderApplication {
            $existing = RiderApplication::where('user_id', $user->id)->lockForUpdate()->first();
            abort_if(in_array($existing?->status, ['pending', 'seller_accepted'], true), 422, 'Your application is already under review.');

            return RiderApplication::updateOrCreate(['user_id' => $user->id], $data + [
                'status' => 'pending',
                'rejection_reason' => null,
                'reviewed_by' => null,
                'reviewed_at' => null,
            ]);
        });

        if (! $user->phone) {
            $user->forceFill(['phone' => $data['phone']])->save();
        }

        // Sellers whose stores they picked decide (Seller Center → Local delivery), and are told.
        foreach (Store::query()->whereIn('id', $data['preferred_store_ids'])->whereNotNull('shop_id')->with('shop.seller')->get() as $picked) {
            if ($picked->shop?->seller) {
                \App\Support\SellerNotify::send($picked->shop->seller, $user, 'New rider application', "{$user->name} applied to deliver for your store. Review it in Seller Center → Local delivery → Applications.");
            }
        }

        return response()->json(['data' => $application->load('store:id,name,city')], 201);
    }
}
