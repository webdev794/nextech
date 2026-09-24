<?php

namespace App\Support;

use App\Models\Product;

/**
 * Shared photo-gallery sync for the admin and seller product forms — a full
 * replace (a photo set has no existing-order integrity concern, so every save
 * simply deletes and recreates rather than upserting by id).
 */
class ProductImages
{
    public const MAX_IMAGES = 8;

    /**
     * @param  array<int, string>|null  $urls  null = leave the gallery alone
     * @param  bool  $firstIsMain  true (seller): the first photo always becomes
     *                             image_url. false (admin, who picks the main
     *                             image separately): only fill image_url from
     *                             the gallery when it's empty.
     */
    public static function sync(Product $product, ?array $urls, bool $firstIsMain = true): void
    {
        if ($urls === null) {
            return;
        }

        $urls = array_values(array_filter($urls));

        $product->images()->delete();
        foreach ($urls as $index => $url) {
            $product->images()->create(['url' => $url, 'sort_order' => $index]);
        }

        // The home page cards, admin list and product page's default swatch
        // all read image_url, not the gallery.
        if ($firstIsMain || ! $product->image_url) {
            $product->update(['image_url' => $urls[0] ?? ($firstIsMain ? null : $product->image_url)]);
        }
    }
}
