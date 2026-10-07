<?php

namespace App\Support;

use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;

/**
 * What a buyer bought, frozen on the order line, and which parts of a listing
 * can't change once it has sold (like Amazon: a different product is a new listing).
 */
class ProductSnapshot
{
    /** Listing fields fixed once the product has sold. */
    public const LOCKED = ['name', 'category_id', 'product_type', 'trademark_id'];

    /** Product details fixed once sold (the model number identifies the product). */
    public const LOCKED_DETAILS = ['model_number'];

    /** @return array<string, mixed> */
    public static function of(Product $product, ?ProductVariant $variant = null): array
    {
        $product->loadMissing(['category', 'shop:id,name']);
        $details = (array) ($product->product_details ?? []);

        return array_filter([
            'name' => $product->name,
            'variant' => $variant?->label,
            'category' => $product->category?->path(),
            'seller' => $product->shop?->name,
            'description' => $product->description,
            'bullet_points' => $product->bullet_points,
            'product_details' => $details ?: null,
            'warranty' => $details['warranty'] ?? null,
            'warranty_terms' => $details['warranty_terms'] ?? null,
            'return_days' => $product->return_days,
            'return_policy' => $product->return_policy,
            'image_url' => $variant?->image_url ?: $product->image_url,
            'captured_at' => now()->toIso8601String(),
        ], fn ($v) => $v !== null && $v !== '' && $v !== []);
    }

    /** Has anyone bought it (an order that wasn't cancelled)? */
    public static function hasSold(Product $product): bool
    {
        return OrderItem::where('product_id', $product->id)->whereHas('order', fn ($q) => $q->where('status', '!=', 'cancelled'))->exists();
    }

    /** Why this update can't be saved (a locked field changed), or null. */
    public static function lockedChange(Product $product, array $data): ?string
    {
        if (! self::hasSold($product)) {
            return null;
        }
        $changed = collect(self::LOCKED)->filter(fn ($k) => array_key_exists($k, $data) && (string) ($data[$k] ?? '') !== (string) ($product->{$k} ?? ''));
        $before = (array) ($product->product_details ?? []);
        foreach (self::LOCKED_DETAILS as $k) {
            if (array_key_exists('product_details', $data) && array_key_exists($k, (array) $data['product_details']) && (string) ($data['product_details'][$k] ?? '') !== (string) ($before[$k] ?? '')) {
                $changed->push($k);
            }
        }
        if ($changed->isEmpty()) {
            return null;
        }
        $labels = ['name' => 'name', 'category_id' => 'category', 'product_type' => 'product type', 'trademark_id' => 'brand', 'model_number' => 'model number'];

        return 'This product has been sold, so its '.$changed->map(fn ($k) => $labels[$k] ?? $k)->unique()->implode(', ').' can’t change — buyers’ orders and warranties refer to it. To sell a different product, add it as a new listing.';
    }
}
