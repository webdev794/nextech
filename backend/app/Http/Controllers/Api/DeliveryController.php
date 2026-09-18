<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Support\CheckoutFees;
use App\Support\Geo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeliveryController extends Controller
{
    /**
     * Given a lat/lng, report the nearest active store, distance, whether it's
     * in that store's delivery radius, and an estimated delivery time.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $fees = CheckoutFees::current();

        $stores = Store::query()->where('is_active', true)
            ->whereNotNull('latitude')->whereNotNull('longitude')->get();

        if ($stores->isEmpty()) {
            return response()->json(['data' => [
                'configured' => false,
                'deliverable' => true,
                'minutes' => 15,
                'delivery_mode' => $fees['delivery_mode'],
                'delivery_fee_cents' => CheckoutFees::distanceFeeCents($fees, null, null),
            ]]);
        }

        // Deliverable when the point is inside *any* active store's radius; the
        // nearest such store serves it. Otherwise fall back to the nearest store
        // for the "how far out of range" numbers.
        $serving = Geo::servingStore($stores, (float) $data['lat'], (float) $data['lng']);
        $best = $serving ?? Geo::nearestStore($stores, (float) $data['lat'], (float) $data['lng']);
        $km = $best['km'];
        $radiusKm = (float) $best['store']->delivery_radius_km;

        // ~25 km/h effective delivery speed, plus a 6 minute prep floor.
        $minutes = max(6, (int) ceil(6 + ($km / 25) * 60));

        return response()->json(['data' => [
            'configured' => true,
            'deliverable' => $serving !== null,
            'distance_km' => round($km, 2),
            'radius_km' => $best['store']->delivery_radius_km,
            'minutes' => $minutes,
            'store_name' => $best['store']->name,
            'store_id' => $best['store']->id,
            'delivery_mode' => $fees['delivery_mode'],
            'delivery_fee_cents' => CheckoutFees::distanceFeeCents($fees, $km, $radiusKm),
        ]]);
    }
}
