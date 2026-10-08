<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderPackage;
use App\Models\Product;
use App\Support\CheckoutFees;
use App\Support\Market;
use App\Support\SalesTax;
use App\Support\SellerFulfillment;
use App\Support\SellerProgress;
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
            'postal_code' => ['sometimes', 'nullable', 'string', 'max:12'],
            'latitude' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
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
            if (! $product || $product->isDigital() || ! $product->shop?->shipsItself()) {
                continue;
            }
            $sellerShipped[] = $product->id;
            $lines[] = ['product' => $product, 'quantity' => $line['quantity'], 'line_total_cents' => $line['price_cents'] * $line['quantity']];
        }

        // The cart's goods total, for the cash-on-delivery maximum.
        $codTotal = (int) collect($data['lines'] ?? [])->sum(fn ($l) => (int) ($l['price_cents'] ?? 0) * (int) ($l['quantity'] ?? 0));
                $quote = SellerShipping::quote($lines, $data['state'] ?? null, null, SellerShipping::addressType($data), ($request->header('X-Market') || $request->input('market')) ? Market::fromRequest($request) : null,
            // A street address or map pin lets sellers' own local delivery apply.
            (! empty($data['line1']) || isset($data['latitude'], $data['longitude'])) ? $data : null);

        return response()->json(['data' => $quote + [
            'seller_shipped_product_ids' => array_values(array_unique($sellerShipped)),
            // Cash on delivery for this cart: null = allowed, otherwise why not.
            'cod_blocked' => SellerProgress::codBlockedReason($products->values(), Market::fromRequest($request), $codTotal),
            'state_known' => SellerShipping::stateCode($data['state'] ?? null, Market::fromRequest($request)) !== null,
            // Sales tax for this address, for the cart's estimate (checkout recalculates it).
            'tax_rate_bps' => SalesTax::rateBps(Market::fromRequest($request), $data['state'] ?? null, $data['postal_code'] ?? null, (int) CheckoutFees::current(Market::fromRequest($request))['tax_rate_bps']),
        ]]);
    }

    /** The customer confirms a seller-shipped package arrived. */
    public function received(Request $request, Order $order, OrderPackage $package): JsonResponse
    {
        abort_unless($order->user_id === $request->user()->id && $package->order_id === $order->id, 404);
        abort_if($package->status === 'delivered', 422, 'Already marked as received.');
        abort_unless(in_array($package->status, SellerProgress::MOVING, true), 422, 'This package can’t be marked received.');

        // Recorded as the buyer's step; cash on delivery is still confirmed by the seller.
        SellerProgress::advance($package, 'delivered', 'buyer');

        return response()->json(['data' => $package->fresh()]);
    }
}
