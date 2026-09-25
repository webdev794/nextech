<?php

namespace App\Support;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductReview;
use Illuminate\Database\Eloquent\Builder;

/** Shared rules for buyer reviews (App\Http\Controllers\Api\ReviewController and the admin side). */
class Reviews
{
    /** "Kirsten Gelevan" -> "Kirsten G." — reviewers are shown by first name and initial. */
    public static function publicName(?string $name): string
    {
        $parts = preg_split('/\s+/u', trim((string) $name), -1, PREG_SPLIT_NO_EMPTY);
        if (! $parts) {
            return 'NexTech shopper';
        }

        return $parts[0].(count($parts) > 1 ? ' '.mb_strtoupper(mb_substr(end($parts), 0, 1)).'.' : '');
    }

    /** The buyer has the item: the order was delivered, or the seller's package with it was. */
    public static function received(Order $order, OrderItem $item): bool
    {
        if ($order->status === 'completed') {
            return true;
        }

        return $order->packages()->where('status', 'delivered')->whereHas('items', fn ($q) => $q->where('order_item_id', $item->id))->exists();
    }

    /**
     * Average, count and how many reviews gave each star.
     *
     * @param  Builder<ProductReview>  $base
     * @return array{average: float, count: int, by_star: array<int, int>}
     */
    public static function summary(Builder $base): array
    {
        $counts = (clone $base)->selectRaw('rating, COUNT(*) as n')->groupBy('rating')->pluck('n', 'rating');
        $byStar = [];
        foreach ([5, 4, 3, 2, 1] as $star) {
            $byStar[$star] = (int) ($counts[$star] ?? 0);
        }
        $count = array_sum($byStar);

        return [
            'average' => $count ? round(array_sum(array_map(fn ($s, $n) => $s * $n, array_keys($byStar), $byStar)) / $count, 1) : 0.0,
            'count' => $count,
            'by_star' => $byStar,
        ];
    }

    /** @return array<string, mixed> */
    public static function present(ProductReview $review): array
    {
        $product = $review->product;
        $available = $product && $product->is_active && $product->status === 'approved' && (! $product->shop_id || $product->shop?->is_active);

        return [
            'id' => $review->id,
            'rating' => $review->rating,
            'body' => $review->body,
            'fit' => $review->fit,
            'variant_label' => $review->variant_label,
            'images' => $review->images ?? [],
            'helpful_count' => $review->helpful_count,
            'created_at' => $review->created_at,
            'reviewer' => ['id' => $review->user_id, 'name' => self::publicName($review->user?->name)],
            'product' => $product ? [
                'id' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug,
                'image_url' => $product->image_url,
                'price_cents' => $product->price_cents,
                'currency' => Market::currency($product->market),
                'available' => $available && $product->inventory_quantity + $product->variants()->where('is_active', true)->sum('inventory_quantity') > 0,
                'listed' => (bool) $available,
            ] : null,
        ];
    }

    /**
     * Keep the product's rating in step with its approved reviews. Products
     * without any real review yet keep the rating they already had.
     */
    public static function refreshProductRating(int $productId): void
    {
        $summary = self::summary(ProductReview::where('product_id', $productId)->where('status', 'approved'));
        if ($summary['count'] > 0) {
            Product::whereKey($productId)->update(['rating_avg' => $summary['average'], 'rating_count' => $summary['count']]);
        }
    }
}
