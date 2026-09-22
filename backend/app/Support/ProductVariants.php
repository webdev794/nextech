<?php

namespace App\Support;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\QueryException;

/**
 * Shared variant sync — incremental upsert-by-id, used by both the admin
 * product form and the seller product form: a row with `id` updates, a row
 * without one creates, and `_delete: true` deletes (blocked with a friendly
 * 422 if it's on an existing order).
 */
class ProductVariants
{
    /**
     * @param  array<int, array<string, mixed>>|null  $rows
     */
    public static function sync(Product $product, ?array $rows): void
    {
        if ($rows === null) {
            return;
        }

        foreach ($rows as $index => $row) {
            $existing = ! empty($row['id'])
                ? $product->variants()->whereKey($row['id'])->first()
                : null;

            if (! empty($row['_delete'])) {
                if ($existing) {
                    try {
                        $existing->delete();
                    } catch (QueryException) {
                        abort(422, "\"{$existing->label}\" is on an existing order — deactivate it instead of deleting.");
                    }
                }

                continue;
            }

            $skuOwner = ProductVariant::where('sku', $row['sku'])->first();
            if ($skuOwner && $skuOwner->id !== ($existing->id ?? null)) {
                abort(422, "The variant SKU \"{$row['sku']}\" is already in use.");
            }

            $compareAt = $row['compare_at_price_cents'] ?? null;

            $attributes = [
                'label' => $row['label'],
                'sku' => $row['sku'],
                'price_cents' => (int) $row['price_cents'],
                'compare_at_price_cents' => ($compareAt === null || $compareAt === '') ? null : (int) $compareAt,
                'inventory_quantity' => (int) ($row['inventory_quantity'] ?? 0),
                'image_url' => $row['image_url'] ?? null,
                'sort_order' => (int) ($row['sort_order'] ?? $index),
                'is_active' => (bool) ($row['is_active'] ?? true),
            ];

            if ($existing) {
                $existing->update($attributes);
            } else {
                $product->variants()->create($attributes);
            }
        }
    }
}
