<?php

namespace App\Support;

use App\Models\Product;
use Illuminate\Database\QueryException;

/**
 * Shared variant sync — incremental upsert-by-id, used by both the admin
 * product form and the seller product form: a row with `id` updates, a row
 * without one creates, and `_delete: true` deletes (blocked with a friendly
 * 422 if it's on an existing order). SKUs are always system-generated
 * (App\Support\Sku) — never taken from the request, and never changed once
 * assigned.
 */
class ProductVariants
{
    /**
     * @param  array<int, array<string, mixed>>|null  $rows
     * @return array<int, int> maps each surviving row's position in `$rows`
     *                          to its variant id — callers (e.g. per-store
     *                          stock sync) that need to reference "the 2nd
     *                          variant row" use this instead of a client SKU.
     */
    public static function sync(Product $product, ?array $rows): array
    {
        if ($rows === null) {
            return [];
        }

        $indexToId = [];

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

            $compareAt = $row['compare_at_price_cents'] ?? null;

            $attributes = [
                'label' => $row['label'],
                'price_cents' => (int) $row['price_cents'],
                'compare_at_price_cents' => ($compareAt === null || $compareAt === '') ? null : (int) $compareAt,
                'inventory_quantity' => (int) ($row['inventory_quantity'] ?? 0),
                'image_url' => $row['image_url'] ?? null,
                'sort_order' => (int) ($row['sort_order'] ?? $index),
                'is_active' => (bool) ($row['is_active'] ?? true),
            ];

            if ($existing) {
                $existing->update($attributes);
                $indexToId[$index] = $existing->id;
            } else {
                $attributes['sku'] = Sku::nextVariantSku($product);
                $variant = $product->variants()->create($attributes);
                $indexToId[$index] = $variant->id;
            }
        }

        return $indexToId;
    }
}
