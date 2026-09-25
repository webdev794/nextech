<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use App\Models\Trademark;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Performance -> Account health -> Trademarks: a seller registers the brands
 * they sell under; NexTech reviews each one (AdminTrademarkController) before
 * it can be chosen on a product. A logo can be added at any time.
 */
class SellerTrademarkController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->shop($request)->trademarks()->latest()->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $shop = $this->shop($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('trademarks')->where('shop_id', $shop->id)],
            'registration_number' => ['required', 'string', 'max:60'],
            'registration_country' => ['required', 'string', 'size:2'],
            'certificate_path' => ['required', 'string', 'max:255', 'starts_with:kyc/'.$request->user()->id.'/'],
            'logo_url' => ['nullable', 'string', 'max:500'],
        ], ['certificate_path.required' => 'Upload the trademark registration certificate.']);

        $trademark = $shop->trademarks()->create($data + ['status' => 'pending']);

        return response()->json(['data' => $trademark], 201);
    }

    /** Add or change the logo (any time), or fix and resubmit a rejected trademark. */
    public function update(Request $request, Trademark $trademark): JsonResponse
    {
        $shop = $this->shop($request);
        abort_unless($trademark->shop_id === $shop->id, 404);
        $data = $request->validate([
            'logo_url' => ['sometimes', 'nullable', 'string', 'max:500'],
            'registration_number' => ['sometimes', 'string', 'max:60'],
            'registration_country' => ['sometimes', 'string', 'size:2'],
            'certificate_path' => ['sometimes', 'string', 'max:255', 'starts_with:kyc/'.$request->user()->id.'/'],
        ]);

        // Changing the registration itself sends it back for review; a logo doesn't.
        $resubmit = (bool) array_intersect_key($data, array_flip(['registration_number', 'registration_country', 'certificate_path']));
        $trademark->update($data + ($resubmit ? ['status' => 'pending', 'note' => null, 'reviewed_at' => null] : []));

        return response()->json(['data' => $trademark->fresh()]);
    }

    private function shop(Request $request): Shop
    {
        $seller = $request->user()->seller;
        abort_unless($seller?->status === 'approved' && $seller->shop, 403, 'Approved seller access required.');

        return $seller->shop;
    }
}
