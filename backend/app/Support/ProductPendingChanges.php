<?php

namespace App\Support;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Arr;

/**
 * Edits to a live seller product: held for review while the live version
 * keeps selling, applied when admin approves, dropped when admin rejects.
 * Stock counts aren't reviewed — they apply right away so nothing oversells.
 */
class ProductPendingChanges
{
    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, array<string, mixed>>|null  $variants
     * @param  array<int, string>|null  $images
     */
    public static function hold(Product $product, array $data, ?array $variants, ?array $images): void
    {
        $data = Arr::except($data, ['status', 'rejection_reason', 'is_active']);

        // Not shown to buyers: a category request reaches NexTech right away.
        if (array_key_exists('suggested_category_name', $data)) {
            $product->forceFill(['suggested_category_name' => $data['suggested_category_name']]);
        }
        if (array_key_exists('inventory_quantity', $data)) {
            $product->forceFill(['inventory_quantity' => (int) $data['inventory_quantity']]);
        }
        foreach ($variants ?? [] as $v) {
            if (! empty($v['id']) && empty($v['_delete']) && array_key_exists('inventory_quantity', $v)) {
                ProductVariant::where('product_id', $product->id)->whereKey($v['id'])->update(['inventory_quantity' => (int) $v['inventory_quantity']]);
            }
        }

        $product->forceFill([
            'pending_changes' => ['data' => $data, 'variants' => $variants, 'images' => $images],
            'pending_submitted_at' => now(),
            'rejection_reason' => null,
        ])->save();
    }

    /** Admin approved: make the held edit live. Call inside a transaction. */
    public static function apply(Product $product): void
    {
        $changes = (array) $product->pending_changes;
        if ($changes === []) {
            return;
        }
        $product->update((array) ($changes['data'] ?? []));
        ProductVariants::sync($product, $changes['variants'] ?? null);
        ProductImages::sync($product, $changes['images'] ?? null);
        $product->forceFill(['pending_changes' => null, 'pending_submitted_at' => null])->save();
        DigitalProducts::syncStock($product->fresh());
    }

    /** Admin rejected the edit: the live version stays as it is. */
    public static function discard(Product $product, string $reason): void
    {
        $product->forceFill(['pending_changes' => null, 'pending_submitted_at' => null, 'rejection_reason' => $reason])->save();
    }

    /**
     * The seller's own view: their held edit over the live product, so the
     * form shows what they submitted.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public static function overlay(array $row): array
    {
        $changes = (array) ($row['pending_changes'] ?? []);
        if ($changes === []) {
            return $row;
        }
        $row = array_merge($row, (array) ($changes['data'] ?? []));
        if (is_array($changes['images'] ?? null)) {
            $row['images'] = array_map(fn ($url) => ['url' => $url], array_values($changes['images']));
        }
        if (is_array($changes['variants'] ?? null)) {
            $row['variants'] = array_values(array_filter($changes['variants'], fn ($v) => empty($v['_delete'])));
        }

        return $row;
    }
}
