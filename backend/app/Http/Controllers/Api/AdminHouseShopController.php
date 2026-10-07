<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\HouseShop;
use App\Support\Market;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Admin → Settings → House shop: the owner's own shop on the seller tools. */
class AdminHouseShopController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json(['data' => $this->payload()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'line1' => ['required', 'string', 'max:255'],
            'line2' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:100'],
            'state' => ['required', 'string', Rule::in(array_keys(Market::states(Market::home())))],
            'postal_code' => ['required', 'string', 'max:12'],
            'tax_id' => ['nullable', 'string', 'max:60'],
        ]);
        HouseShop::create($request->user(), $data);

        return response()->json(['data' => $this->payload()], 201);
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        $shop = HouseShop::current();

        return [
            'shop' => $shop ? [
                'id' => $shop->id, 'name' => $shop->name, 'slug' => $shop->slug, 'market' => $shop->market,
                'fulfillment_mode' => $shop->fulfillment_mode,
                'owner' => $shop->seller?->user ? ['id' => $shop->seller->user->id, 'name' => $shop->seller->user->name, 'email' => $shop->seller->user->email] : null,
                'products' => $shop->products()->count(),
            ] : null,
            'market' => Market::home(),
            'states' => Market::states(Market::home()),
        ];
    }
}
