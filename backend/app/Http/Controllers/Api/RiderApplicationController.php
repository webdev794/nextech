<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RiderApplication;
use App\Models\Store;
use App\Support\Geo;
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

        $stores = Store::query()
            ->where('is_active', true)
            ->withCount(['riders' => fn ($q) => $q->where('is_rider', true)])
            ->get(['id', 'name', 'line1', 'city', 'state', 'postal_code', 'latitude', 'longitude'])
            ->map(function (Store $store) use ($lat, $lng) {
                $km = ($lat !== null && $lng !== null && $store->latitude !== null && $store->longitude !== null)
                    ? Geo::haversineKm($lat, $lng, (float) $store->latitude, (float) $store->longitude)
                    : null;

                return [
                    'id' => $store->id,
                    'name' => $store->name,
                    'address' => implode(', ', array_filter([$store->line1, $store->city, trim($store->state.' '.$store->postal_code)])),
                    'riders_count' => (int) $store->riders_count,
                    'distance_miles' => $km !== null ? round($km / 1.609344, 1) : null,
                ];
            })
            ->sortBy(fn ($s) => $s['distance_miles'] ?? PHP_FLOAT_MAX)
            ->values();

        return response()->json(['data' => [
            'stores' => $stores,
            'located' => $lat !== null && $lng !== null ? ['lat' => $lat, 'lng' => $lng] : null,
        ]]);
    }

    /** Submit (or, after a rejection, resubmit) an application. */
    public function apply(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_if($user->is_rider, 422, 'You are already a rider.');
        abort_if($user->is_admin, 422, 'Administrator accounts cannot apply as riders.');

        $data = $request->validate([
            'store_id' => ['required', 'integer', Rule::exists('stores', 'id')->where('is_active', true)],
            'phone' => ['required', 'string', 'max:40'],
            'home_address' => ['required', 'string', 'max:255'],
            'home_lat' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'home_lng' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
            'vehicle_type' => ['required', Rule::in(self::VEHICLES)],
            'license_number' => ['required_unless:vehicle_type,bicycle', 'nullable', 'string', 'max:60'],
            // From POST /seller/kyc-document (kind=license_document) — must be
            // the caller's own private upload.
            'license_document_path' => ['required_unless:vehicle_type,bicycle', 'nullable', 'string', 'max:255', 'starts_with:kyc/'.$user->id.'/'],
        ]);

        if (empty($data['home_lat']) || empty($data['home_lng'])) {
            [$data['home_lat'], $data['home_lng']] = Geo::geocode($data['home_address']);
        }

        $application = DB::transaction(function () use ($user, $data): RiderApplication {
            $existing = RiderApplication::where('user_id', $user->id)->lockForUpdate()->first();
            abort_if($existing?->status === 'pending', 422, 'Your application is already under review.');

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

        return response()->json(['data' => $application->load('store:id,name,city')], 201);
    }
}
