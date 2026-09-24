<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Support\Geo;
use App\Support\Market;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminStoreController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(['data' => Store::where('country', Market::fromRequest($request))->orderBy('name')->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        // A new store opens in the country the admin is working in.
        return response()->json(['data' => Store::create($this->validated($request) + ['country' => Market::fromRequest($request)])], 201);
    }

    public function update(Request $request, Store $store): JsonResponse
    {
        $store->update($this->validated($request));

        return response()->json(['data' => $store->fresh()]);
    }

    public function destroy(Store $store): JsonResponse
    {
        $store->delete();

        return response()->json(status: 204);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'line1' => ['required', 'string', 'max:255'],
            'line2' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:100'],
            'state' => ['required', 'string', 'max:60'],
            'country' => ['sometimes', Rule::in(Market::codes())],
            'postal_code' => ['required', 'string', 'max:12'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'delivery_radius_km' => ['sometimes', 'integer', 'min:1', 'max:200'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        // Geocode from the address when the admin left coordinates blank.
        if (empty($data['latitude']) || empty($data['longitude'])) {
            [$lat, $lng] = Geo::geocode(implode(', ', array_filter([
                $data['line1'], $data['city'], $data['state'], $data['postal_code'],
            ])));
            if ($lat !== null) {
                $data['latitude'] = $lat;
                $data['longitude'] = $lng;
            }
        }

        return $data;
    }
}
