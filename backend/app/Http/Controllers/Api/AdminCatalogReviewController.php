<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\SalesBoostOffer;
use App\Models\Trademark;
use App\Support\Market;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Admin side of seller listings: reviewing the trademarks sellers register,
 * and making sales boost offers (a recommended lower price) on products whose
 * pricing gives them little traffic.
 */
class AdminCatalogReviewController extends Controller
{
    public function trademarks(Request $request): JsonResponse
    {
        $status = $request->validate(['status' => ['sometimes', Rule::in(['pending', 'approved', 'rejected', 'all'])]])['status'] ?? 'pending';

        $trademarks = Trademark::query()
            ->with('shop:id,name,market')
            ->whereHas('shop', fn ($q) => $q->where('market', Market::fromRequest($request)))
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->latest('id')
            ->limit(200)
            ->get();

        return response()->json(['data' => $trademarks]);
    }

    public function reviewTrademark(Request $request, Trademark $trademark): JsonResponse
    {
        $data = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'reject'])],
            'note' => ['required_if:decision,reject', 'nullable', 'string', 'max:500'],
        ], ['note.required_if' => 'Say why the trademark can’t be approved.']);

        $trademark->update([
            'status' => $data['decision'] === 'approve' ? 'approved' : 'rejected',
            'note' => $data['decision'] === 'approve' ? null : $data['note'],
            'reviewed_at' => now(),
        ]);

        return response()->json(['data' => $trademark->fresh('shop:id,name,market')]);
    }

    public function salesBoost(Product $product): JsonResponse
    {
        return response()->json(['data' => $product->salesBoostOffers()->latest('id')->limit(100)->get()]);
    }

    /**
     * Offer recommended prices for a product's variations (or the product
     * itself when it has none). A new offer replaces a still-pending one for
     * the same variation. While any offer is pending the product is "Low
     * traffic" and ranks lower in the storefront.
     */
    public function createSalesBoost(Request $request, Product $product): JsonResponse
    {
        abort_unless($product->shop_id !== null, 422, 'Sales boost offers are for seller products.');
        $data = $request->validate([
            'offers' => ['required', 'array', 'min:1', 'max:30'],
            'offers.*.variant_id' => ['nullable', 'integer'],
            'offers.*.recommended_price_cents' => ['required', 'integer', 'min:1'],
        ]);

        DB::transaction(function () use ($product, $data, $request) {
            foreach ($data['offers'] as $row) {
                $variant = $row['variant_id'] ? $product->variants()->whereKey($row['variant_id'])->firstOrFail() : null;
                $current = $variant ? $variant->price_cents : $product->price_cents;
                abort_unless($row['recommended_price_cents'] < $current, 422, 'A recommended price has to be below the current price.');
                SalesBoostOffer::where('product_id', $product->id)->where('product_variant_id', $variant?->id)->where('status', 'pending')->delete();
                SalesBoostOffer::create([
                    'product_id' => $product->id,
                    'product_variant_id' => $variant?->id,
                    'current_price_cents' => $current,
                    'recommended_price_cents' => $row['recommended_price_cents'],
                    'status' => 'pending',
                    'created_by' => $request->user()->id,
                ]);
            }
        });

        return $this->salesBoost($product);
    }
}
