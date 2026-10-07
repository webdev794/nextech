<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PriceChangeRecord;
use App\Models\Product;
use App\Models\SalesBoostOffer;
use App\Models\Shop;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Pricing health: sales boost offers for "Low traffic" products, grouped per
 * product, and the pricing records left behind. For each variation the seller
 * either adjusts to the recommended price or rejects the offer, which closes
 * that variation. Once no offer is pending the product leaves the page and
 * regains full search and recommendation exposure.
 */
class SellerPricingController extends Controller
{
    public function salesBoost(Request $request): JsonResponse
    {
        $shop = $this->shop($request);

        $products = Product::query()
            ->where('shop_id', $shop->id)
            ->whereHas('salesBoostOffers', fn ($q) => $q->where('status', 'pending'))
            ->with(['variants:id,product_id,label,sku,price_cents,is_active', 'salesBoostOffers' => fn ($q) => $q->latest('id')])
            ->get(['id', 'name', 'sku', 'image_url', 'price_cents']);

        $groups = $products->map(function (Product $product) {
            // This round: every offer made since the oldest one still pending.
            $since = $product->salesBoostOffers->where('status', 'pending')->min('created_at');
            $offers = $product->salesBoostOffers->filter(fn ($o) => $o->created_at >= $since)->values();

            return [
                'product' => $product->only(['id', 'name', 'sku', 'image_url']),
                'offers' => $offers->map(fn (SalesBoostOffer $o) => [
                    'id' => $o->id,
                    'variant' => $o->product_variant_id ? $product->variants->firstWhere('id', $o->product_variant_id)?->only(['id', 'label', 'sku']) : null,
                    'current_price_cents' => $o->current_price_cents,
                    'recommended_price_cents' => $o->recommended_price_cents,
                    'status' => $o->status,
                    'decided_at' => $o->decided_at,
                    'created_at' => $o->created_at,
                ]),
                'processed' => $offers->where('status', '!=', 'pending')->count(),
                'total' => $offers->count(),
                'accepted' => $offers->where('status', 'accepted')->count(),
            ];
        })->values();

        return response()->json(['data' => $groups]);
    }

    /** Accept (adjust to the recommended price) or reject (close the variation) one or many offers. */
    public function decide(Request $request): JsonResponse
    {
        $shop = $this->shop($request);
        $data = $request->validate([
            'offer_ids' => ['required', 'array', 'min:1', 'max:200'],
            'offer_ids.*' => ['integer'],
            'decision' => ['required', Rule::in(['accept', 'reject'])],
        ]);

        $offers = SalesBoostOffer::query()
            ->whereIn('id', $data['offer_ids'])
            ->where('status', 'pending')
            ->whereHas('product', fn ($q) => $q->where('shop_id', $shop->id))
            ->with(['product', 'variant'])
            ->get();
        abort_if($offers->isEmpty(), 422, 'Those offers were already handled.');

        DB::transaction(function () use ($offers, $data) {
            foreach ($offers as $offer) {
                $target = $offer->variant ?? $offer->product;
                if ($data['decision'] === 'accept') {
                    PriceChangeRecord::create([
                        'product_id' => $offer->product_id,
                        'product_variant_id' => $offer->product_variant_id,
                        'old_price_cents' => $target->price_cents,
                        'new_price_cents' => $offer->recommended_price_cents,
                        'source' => 'sales_boost',
                        'sales_boost_offer_id' => $offer->id,
                    ]);
                    $target->update(['price_cents' => $offer->recommended_price_cents]);
                } else {
                    // Rejecting closes that variation (or the whole listing when it has none).
                    $target->update(['is_active' => false]);
                }
                $offer->update(['status' => $data['decision'] === 'accept' ? 'accepted' : 'rejected', 'decided_at' => now()]);
            }
        });

        return response()->json(['data' => ['processed' => $offers->count()]]);
    }

    public function records(Request $request): JsonResponse
    {
        $shop = $this->shop($request);

        $records = PriceChangeRecord::query()
            ->whereHas('product', fn ($q) => $q->where('shop_id', $shop->id))
            ->with(['product:id,name,sku', 'variant:id,label,sku'])
            ->latest('id')
            ->limit(200)
            ->get();

        return response()->json(['data' => $records]);
    }

    private function shop(Request $request): Shop
    {
        $seller = $request->user()->seller;
        abort_unless($seller?->status === 'approved' && $seller->shop, 403, 'Approved seller access required.');

        return $seller->shop;
    }
}
