<?php

namespace App\Support;

use App\Models\Product;

/**
 * Putting a product on a lightning deal (seller for their own products, admin
 * for any): it must already be discounted by at least the minimum (its
 * "compare at" price against its price), and the deal runs for admin's set
 * number of hours from the start time, for the quantity given or until it
 * sells out. Ending it early is always allowed.
 */
class LightningDeals
{
    /** @param  array{starts_at?: ?string, quantity: int}  $data */
    public static function start(Product $product, array $data): Product
    {
        $rules = DealSections::settings();
        abort_if($product->affiliate_url, 422, 'Ads can’t be lightning deals.');
        abort_unless($product->status === 'approved' && $product->is_active, 422, 'Only live products can go on a lightning deal.');
        $pct = DealSections::discountPct($product);
        abort_if($pct < $rules['lightning_min_pct'], 422, "A lightning deal needs at least {$rules['lightning_min_pct']}% off — set a “compare at” price at least that much above the price (now {$pct}% off).");
        $start = isset($data['starts_at']) && $data['starts_at'] ? \Illuminate\Support\Carbon::parse($data['starts_at']) : now();
        abort_if($start->lt(now()->subMinutes(5)), 422, 'Pick a start time from now on.');

        $product->forceFill([
            'lightning_starts_at' => $start,
            'lightning_ends_at' => $start->copy()->addHours($rules['lightning_hours']),
            'lightning_qty' => (int) $data['quantity'],
            'lightning_base_sold' => $start->isPast() ? (int) $product->units_sold : null,
        ])->save();
        DealSections::forget();

        return $product;
    }

    public static function stop(Product $product): Product
    {
        $product->forceFill(['lightning_starts_at' => null, 'lightning_ends_at' => null, 'lightning_qty' => null, 'lightning_base_sold' => null])->save();
        DealSections::forget();

        return $product;
    }
}
