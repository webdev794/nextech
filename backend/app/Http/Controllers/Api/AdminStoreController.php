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
    /**
     * Stores / hubs: NexTech's own stores and sellers' stores (their local-delivery
     * bases), each with its owner. `kind=own|seller` narrows the list.
     */
    public function index(Request $request): JsonResponse
    {
        $stores = Store::query()
            ->when(Market::adminFilter($request), fn ($q, $m) => $q->where('country', $m))
            ->when($request->query('kind') === 'own', fn ($q) => $q->whereNull('shop_id'))
            ->when($request->query('kind') === 'seller', fn ($q) => $q->whereNotNull('shop_id'))
            // A seller's store shows once the seller is approved.
            ->where(fn ($q) => $q->whereNull('shop_id')->orWhereHas('shop.seller', fn ($s) => $s->where('status', 'approved')))
            ->with('shop:id,name,slug,seller_id,local_delivery')->withCount('riders')
            ->orderByRaw('shop_id is not null')->orderBy('name')->get();

        return response()->json(['data' => $stores->map(fn (Store $store) => $store->toArray() + [
            'kind' => $store->shop_id ? 'seller' : 'own',
            'seller_id' => $store->shop?->seller_id,
            'local_delivery_status' => $store->shop_id ? \App\Support\SellerStores::status($store) : ($store->local_delivery_active ? 'on' : 'off'),
        ])]);
    }

    /** The hours this store's riders must be on duty (null = no set hours). */
    public function riderHours(Request $request, Store $store): JsonResponse
    {
        $data = $request->validate(\App\Support\RiderWorkHours::rules());
        $store->forceFill(['rider_hours' => $data['hours'] ? ['days' => array_values(array_unique(array_map('intval', $data['hours']['days']))), 'start' => $data['hours']['start'], 'end' => $data['hours']['end']] : null])->save();

        return response()->json(['data' => $store->fresh()]);
    }

    /** Hiring riders for a store on or off (shown on the rider application page and the "Work with us" link). */
    public function hiring(Request $request, Store $store): JsonResponse
    {
        $data = $request->validate(['open' => ['required', 'boolean']]);
        abort_if($data['open'] && $store->shop_id && ! $store->is_active, 422, 'Turn local delivery on for this seller first.');
        $store->forceFill(['hiring_open' => $data['open']])->save();

        return response()->json(['data' => $store->fresh()]);
    }

    /**
     * A seller store's local delivery: `on` (also unlocks it), `off` (admin turns it
     * off and locks it, with a reason), or the seller's request to turn it off:
     * `approve_off` (riders are told and unlinked) / `keep_on` (with a reason).
     */
    public function localDelivery(Request $request, Store $store): JsonResponse
    {
        // NexTech's own store: riders on or off for it (off = its orders go by courier, even nearby).
        if (! $store->shop_id) {
            $data = $request->validate(['action' => ['required', Rule::in(['on', 'off'])]]);
            abort_if($data['action'] === 'on' && ! $store->riders()->exists(), 422, 'Link a rider to this store first (Riders).');
            $store->forceFill(['local_delivery_active' => $data['action'] === 'on'])->save();

            return response()->json(['data' => $store->fresh()]);
        }
        $data = $request->validate(['action' => ['required', Rule::in(['on', 'off', 'approve_off', 'keep_on'])], 'reason' => ['nullable', 'string', 'max:500']]);
        abort_if(in_array($data['action'], ['off', 'keep_on'], true) && blank($data['reason'] ?? null), 422, 'Say why — the seller gets it as a message.');
        abort_if(in_array($data['action'], ['approve_off', 'keep_on'], true) && ! $store->local_delivery_off_requested_at, 422, 'The seller hasn’t asked to turn local delivery off.');
        match ($data['action']) {
            'on' => \App\Support\SellerStores::adminTurnsOn($store, $request->user()),
            'off' => \App\Support\SellerStores::finishOff($store, $request->user(), reason: $data['reason']),
            'approve_off' => \App\Support\SellerStores::finishOff($store, $request->user(), reason: 'as you asked'),
            'keep_on' => \App\Support\SellerStores::keepOn($store, $request->user(), $data['reason']),
        };

        return response()->json(['data' => $store->fresh('shop:id,name,slug,seller_id')]);
    }

    public function store(Request $request): JsonResponse
    {
        // A new store opens in the country the admin is working in.
        // Admin can also add a store for a seller (their local-delivery base): it takes the seller's country.
        $shopId = $request->validate(['shop_id' => ['nullable', 'integer', 'exists:shops,id']])['shop_id'] ?? null;
        $shop = $shopId ? \App\Models\Shop::find($shopId) : null;
        $store = new Store($this->validated($request));
        // Riders start off: switch them on once the store has riders (or the seller turns local delivery on).
        $store->forceFill(['shop_id' => $shop?->id, 'country' => $shop?->market ?? Market::fromRequest($request), 'local_delivery_active' => $shop !== null])->save();

        return response()->json(['data' => $store->fresh('shop:id,name,slug,seller_id')], 201);
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
