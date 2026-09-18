<?php

namespace App\Support;

use App\Models\Product;
use App\Models\ProductVariant;

/**
 * Resolves the effective price / stock / availability for a line item: the
 * chosen variant when there is one, otherwise the product itself.
 */
class Purchasable
{
    /**
     * @param  int|null  $storeId  when given, stock and "sold here" come from
     *                             the product's per-store inventory for that store
     *                             (falling back to the plain column when the
     *                             product isn't on per-store stock).
     * @return array{price_cents: int, compare_at_price_cents: int|null, inventory_quantity: int, active: bool, sold: bool, label: string|null, per_store: bool}
     */
    public static function resolve(Product $product, ?ProductVariant $variant, ?int $storeId = null): array
    {
        $categoryActive = (bool) $product->category?->is_active;
        $baseActive = $product->is_active && $categoryActive && ($variant ? $variant->is_active : true);

        $availability = $product->availabilityAt($storeId, $variant);

        return [
            'price_cents' => (int) ($variant?->price_cents ?? $product->price_cents),
            'compare_at_price_cents' => self::compareAt($variant, $product),
            'inventory_quantity' => $availability['quantity'],
            // `active` = buyable at all here; `sold` = the store carries the line
            // (false + per_store means "not sold in this area").
            'active' => $baseActive && $availability['sold'],
            'sold' => $availability['sold'],
            'label' => $variant?->label,
            'per_store' => $availability['per_store'],
        ];
    }

    private static function compareAt(?ProductVariant $variant, Product $product): ?int
    {
        $value = $variant ? $variant->compare_at_price_cents : $product->compare_at_price_cents;

        return $value !== null ? (int) $value : null;
    }
}
