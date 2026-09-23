<?php

namespace App\Support;

use App\Models\Product;
use App\Models\Shop;

/**
 * All product/variant SKUs are system-generated — neither admin nor seller
 * ever types one in. Format:
 *   - Admin-added product:  ADM######            (product's own id, zero-padded)
 *   - Seller-added product: SLR{shopCode}####     (shop's 4-char code + a per-shop sequence)
 *   - Variant:               {productSku}-V#      (a per-product sequence)
 *
 * Callers must already be inside a DB transaction — nextForShop() and
 * nextVariantSku() lock a row to hand out a sequence number safely under
 * concurrent requests, but don't open their own transaction.
 */
class Sku
{
    /**
     * Assigns a shop its permanent 4-character code, derived from the shop
     * name and reserved once at shop creation — never recomputed later, so
     * every SKU the shop's products ever get stays stable.
     *
     * The first 3 letters are the "base"; the 4th is normally the name's own
     * 4th letter. If another shop already holds that exact 4-char code (e.g.
     * two shops both starting "Tech…"), the next shop sharing that base gets
     * a digit for the 4th character instead (2, 3, 4, …).
     */
    public static function assignShopCode(string $shopName): string
    {
        $letters = strtoupper(preg_replace('/[^A-Za-z]/', '', $shopName) ?? '');
        $letters = str_pad($letters === '' ? 'SHOP' : $letters, 4, 'X');
        $base3 = substr($letters, 0, 3);

        $candidate = $base3.substr($letters, 3, 1);
        if (! Shop::where('shop_code', $candidate)->exists()) {
            return $candidate;
        }

        for ($digit = 2; $digit <= 9; $digit++) {
            $candidate = $base3.$digit;
            if (! Shop::where('shop_code', $candidate)->exists()) {
                return $candidate;
            }
        }

        // Nine shops sharing the same 3-letter base — vanishingly unlikely,
        // but never refuse to onboard a seller over it.
        $suffix = 10;
        do {
            $candidate = $base3.$suffix;
            $suffix++;
        } while (Shop::where('shop_code', $candidate)->exists());

        return $candidate;
    }

    public static function nextForShop(Shop $shop): string
    {
        $locked = Shop::whereKey($shop->id)->lockForUpdate()->first();
        $seq = $locked->next_product_seq;
        $locked->increment('next_product_seq');

        return sprintf('SLR%s%04d', $locked->shop_code, $seq);
    }

    public static function forAdminProduct(int $productId): string
    {
        return sprintf('ADM%06d', $productId);
    }

    public static function nextVariantSku(Product $product): string
    {
        $locked = Product::whereKey($product->id)->lockForUpdate()->first();
        $seq = $locked->next_variant_seq;
        $locked->increment('next_variant_seq');

        return "{$locked->sku}-V{$seq}";
    }
}
