<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\SalesBoostOffer;
use App\Models\Trademark;
use App\Support\Market;
use App\Support\SellerNotify;
use App\Support\TrademarkChanges;
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
        $status = $request->validate(['status' => ['sometimes', Rule::in(['pending', 'changes', 'approved', 'rejected', 'all'])]])['status'] ?? 'pending';

        $trademarks = Trademark::query()
            ->with('shop:id,name,market')
            ->whereHas('shop', fn ($q) => ($m = Market::adminFilter($request)) ? $q->where('market', $m) : $q)
            ->when($status === 'changes', fn ($q) => $q->whereNotNull('change_status'))
            // "Pending" = everything waiting for admin: new trademarks and change requests.
            ->when($status === 'pending', fn ($q) => $q->where(fn ($w) => $w->where('status', 'pending')->orWhere('change_status', 'pending')))
            ->when(! in_array($status, ['all', 'changes', 'pending'], true), fn ($q) => $q->where('status', $status))
            ->latest('id')
            ->limit(200)
            ->get()
            // What to check: duplicate names, new certificate, buyers under warranty, renamed products.
            ->map(fn (Trademark $t) => $t->toArray() + ['advice' => ($t->status !== 'approved' || $t->change_status) ? TrademarkChanges::advice($t) : []]);

        return response()->json(['data' => $trademarks]);
    }

    public function reviewTrademark(Request $request, Trademark $trademark): JsonResponse
    {
        $data = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'reject', 'approve_change', 'reject_change', 'request_docs'])],
            'note' => [Rule::requiredIf(in_array($request->input('decision'), ['reject', 'reject_change', 'request_docs'], true)), 'nullable', 'string', 'max:500'],
        ], ['note.required' => 'Say what the seller needs to know or do.']);

        // A seller's change request on an approved trademark.
        if (in_array($data['decision'], ['approve_change', 'reject_change', 'request_docs'], true)) {
            abort_unless($trademark->change_status !== null, 422, 'No change request on this trademark.');
            $seller = $trademark->shop?->seller;
            if ($data['decision'] === 'approve_change') {
                if ($until = TrademarkChanges::coveredUntil($trademark)) {
                    abort(422, 'Buyers are still covered by returns / warranty until '.$until->format('j M Y').' — it can’t be changed before then.');
                }
                $old = $trademark->name;
                TrademarkChanges::apply($trademark);
                $seller && SellerNotify::send($seller, $request->user(), "Trademark change approved", "Your change to the trademark “{$old}” is approved".($trademark->name !== $old ? " — it’s now “{$trademark->name}” on all its products." : '.'));
            } elseif ($data['decision'] === 'request_docs') {
                $trademark->forceFill(['change_status' => 'docs_requested', 'change_note' => $data['note']])->save();
                $seller && SellerNotify::send($seller, $request->user(), "Documents needed for “{$trademark->name}”", "Before we can change the trademark “{$trademark->name}”, please upload: {$data['note']} Then send the change request again from Account health.");
            } else {
                $trademark->forceFill(['change_request' => null, 'change_status' => null, 'change_note' => $data['note']])->save();
                $seller && SellerNotify::send($seller, $request->user(), "Trademark change not approved", "Your change to the trademark “{$trademark->name}” wasn’t approved: {$data['note']} The trademark stays as it was.");
            }

            return response()->json(['data' => $trademark->fresh('shop:id,name,market')]);
        }

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
