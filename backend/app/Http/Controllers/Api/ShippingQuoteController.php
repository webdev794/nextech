<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderPackage;
use App\Models\Product;
use App\Support\Market;
use App\Support\SellerFulfillment;
use App\Support\SellerShipping;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Storefront side of seller shipping: the checkout estimate for seller-shipped
 * items (fees + delivery dates per seller, before the order is placed), and
 * the customer confirming a seller's package arrived.
 */
class ShippingQuoteController extends Controller
{
    public function quote(Request $request): JsonResponse
    {
        $data = $request->validate([
            'state' => ['sometimes', 'nullable', 'string', 'max:60'],
            'line1' => ['sometimes', 'nullable', 'string', 'max:255'],
            'line2' => ['sometimes', 'nullable', 'string', 'max:255'],
            'city' => ['sometimes', 'nullable', 'string', 'max:100'],
            'lines' => ['required', 'array', 'max:100'],
            'lines.*.product_id' => ['required', 'integer'],
            'lines.*.quantity' => ['required', 'integer', 'min:1'],
            'lines.*.price_cents' => ['required', 'integer', 'min:0'],
        ]);

        $products = Product::with('shop')->whereIn('id', collect($data['lines'])->pluck('product_id'))->get()->keyBy('id');
        $lines = [];
        $sellerShipped = [];
        foreach ($data['lines'] as $line) {
            $product = $products->get($line['product_id']);
            if (! $product || ! $product->shop?->shipsItself()) {
                continue;
            }
            $sellerShipped[] = $product->id;
            $lines[] = ['product' => $product, 'quantity' => $line['quantity'], 'line_total_cents' => $line['price_cents'] * $line['quantity']];
        }

        $quote = SellerShipping::quote($lines, $data['state'] ?? null, null, SellerShipping::addressType($data));

        return response()->json(['data' => $quote + [
            'seller_shipped_product_ids' => array_values(array_unique($sellerShipped)),
            'state_known' => SellerShipping::stateCode($data['state'] ?? null, Market::fromRequest($request)) !== null,
        ]]);
    }

    /** The customer confirms a seller-shipped package arrived. */
    public function received(Request $request, Order $order, OrderPackage $package): JsonResponse
    {
        abort_unless($order->user_id === $request->user()->id && $package->order_id === $order->id, 404);
        abort_if($package->status === 'delivered', 422, 'Already marked as received.');

        $package->update(['status' => 'delivered', 'delivered_at' => now()]);
        SellerFulfillment::sync($order);

        return response()->json(['data' => $package->fresh()]);
    }
}
