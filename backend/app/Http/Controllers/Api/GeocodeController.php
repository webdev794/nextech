<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Support\Geo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GeocodeController extends Controller
{
    /**
     * Forward address search, biased toward a store's area so results are local
     * whether the store is in the USA, India, or anywhere else. Pass the map's
     * current `lat`/`lng` and the bias follows the store nearest that point, so a
     * multi-store shop serves customers in every city it covers — not just the
     * first store's.
     */
    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:3', 'max:200'],
            'lat' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'lng' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
        ]);

        [$lat, $lng, $maxKm] = $this->biasCentre(
            isset($validated['lat']) ? (float) $validated['lat'] : null,
            isset($validated['lng']) ? (float) $validated['lng'] : null,
        );

        return response()->json(['data' => Geo::search($validated['q'], $lat, $lng, $maxKm)]);
    }

    /**
     * Reverse geocode a dropped pin to a readable address.
     */
    public function reverse(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
        ]);

        return response()->json(['data' => Geo::reverse((float) $validated['lat'], (float) $validated['lng'])]);
    }

    /**
     * Centre + how far a forward-search result may sit from it (roughly four
     * delivery radii, so results stay in the serviceable neighbourhood). With a
     * map position, the centre is the store nearest it; otherwise the first
     * active store. With no stores at all, bias to the map position itself.
     *
     * @return array{0: float|null, 1: float|null, 2: float}
     */
    private function biasCentre(?float $lat = null, ?float $lng = null): array
    {
        $stores = Store::query()->where('is_active', true)
            ->whereNotNull('latitude')->whereNotNull('longitude')
            ->orderBy('id')->get();

        if ($stores->isEmpty()) {
            return [$lat, $lng, 40.0];
        }

        $store = ($lat !== null && $lng !== null)
            ? Geo::nearestStore($stores, $lat, $lng)['store']
            : $stores->first();

        return [(float) $store->latitude, (float) $store->longitude, max(25.0, $store->delivery_radius_km * 4.0)];
    }
}
