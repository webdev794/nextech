<?php

namespace App\Support;

use App\Models\Product;
use Illuminate\Support\Carbon;

/**
 * Lightning deals (seller for their own products, admin for any): a % off the
 * regular price while the deal runs — fixed, or picked each round from a range
 * — for admin's set number of hours from the start, for the units given or
 * until they sell out; then the price goes back. With "auto-restart" a new
 * round begins when one ends, at a slightly different % from the range. At
 * most `lightning_max_share` % of an owner's live products can be on one.
 */
class LightningDeals
{
    /** @param  array{starts_at?: ?string, quantity: int, pct_min: int, pct_max?: ?int, repeat?: bool}  $data */
    public static function start(Product $product, array $data): Product
    {
        $rules = DealSections::settings();
        abort_if($product->affiliate_url, 422, 'Ads can’t be lightning deals.');
        abort_unless($product->status === 'approved' && $product->is_active, 422, 'Only live products can go on a lightning deal.');
        $min = (int) $data['pct_min'];
        $max = (int) ($data['pct_max'] ?? $min) ?: $min;
        abort_if($min < $rules['lightning_min_pct'], 422, "A lightning deal needs at least {$rules['lightning_min_pct']}% off.");
        abort_if($max < $min, 422, 'The highest % must be at least the lowest %.');
        $start = ! empty($data['starts_at']) ? Carbon::parse($data['starts_at']) : now();
        abort_if($start->lt(now()->subMinutes(5)), 422, 'Pick a start time from now on.');

        // Keep deals special: only a share of the owner's live products at once.
        if (! ($product->lightning_ends_at?->isFuture())) {
            $owned = Product::query()->where('status', 'approved')->where('is_active', true)->whereNull('affiliate_url')
                ->when($product->shop_id, fn ($q) => $q->where('shop_id', $product->shop_id), fn ($q) => $q->whereNull('shop_id'));
            $cap = max(1, (int) floor((clone $owned)->count() * $rules['lightning_max_share'] / 100));
            $onDeal = (clone $owned)->where('lightning_ends_at', '>', now())->count();
            abort_if($onDeal >= $cap, 422, "You can have up to {$rules['lightning_max_share']}% of your products on lightning deals at once ({$cap} now) — end one first, or add more products.");
        }

        // Keep the section varied: a fair share per category, and a limit for the whole store.
        if (! ($product->lightning_ends_at?->isFuture())) {
            $running = Product::query()->where('lightning_ends_at', '>', now())->whereNotNull('lightning_pct')
                ->when($product->market, fn ($q) => $q->where('market', $product->market));
            $categories = max(1, Product::query()->where('status', 'approved')->where('is_active', true)
                ->when($product->market, fn ($q) => $q->where('market', $product->market))->distinct()->count('category_id'));
            $perCategory = max(2, (int) ceil($rules['lightning_max'] / $categories));
            $name = $product->category?->name ?? 'this category';
            abort_if((clone $running)->count() >= $rules['lightning_max'] * 2, 422, 'Lightning deals are full right now — try again when some end. Meanwhile, list products in other categories to reach more buyers.');
            abort_if((clone $running)->where('category_id', $product->category_id)->count() >= $perCategory, 422, "Lightning deals in {$name} are full right now ({$perCategory} running). Try again when one ends — or add products in other categories, where there's room.");
        }

        $product->forceFill([
            'lightning_pct_min' => $min,
            'lightning_pct_max' => $max,
            'lightning_repeat' => (bool) ($data['repeat'] ?? false),
            'lightning_qty' => (int) $data['quantity'],
        ]);
        self::round($product, $start);
        DealSections::forget();

        return $product;
    }

    /** Begin a round at $start with a % picked from the range. */
    private static function round(Product $product, Carbon $start): void
    {
        $rules = DealSections::settings();
        $product->forceFill([
            'lightning_pct' => random_int((int) $product->lightning_pct_min, (int) max($product->lightning_pct_min, $product->lightning_pct_max)),
            'lightning_starts_at' => $start,
            'lightning_ends_at' => $start->copy()->addHours($rules['lightning_hours']),
            'lightning_base_sold' => $start->isPast() ? (int) $product->units_sold : null,
        ])->save();
    }

    public static function stop(Product $product): Product
    {
        $product->forceFill([
            'lightning_starts_at' => null, 'lightning_ends_at' => null, 'lightning_qty' => null, 'lightning_base_sold' => null,
            'lightning_pct' => null, 'lightning_pct_min' => null, 'lightning_pct_max' => null, 'lightning_repeat' => false,
        ])->save();
        DealSections::forget();

        return $product;
    }

    /** Scheduled: start the next round of auto-restarting deals that ended or sold out. Returns how many. */
    public static function restartDue(): int
    {
        $n = 0;
        Product::query()->where('lightning_repeat', true)->whereNotNull('lightning_ends_at')
            ->where('is_active', true)->where('status', 'approved')
            ->each(function (Product $p) use (&$n) {
                $soldOut = $p->lightning_starts_at?->isPast() && DealSections::lightningClaimed($p) >= (int) $p->lightning_qty;
                if ($p->lightning_ends_at->isPast() || $soldOut) {
                    self::round($p, now());
                    $n++;
                }
            });
        if ($n) {
            DealSections::forget();
        }

        return $n;
    }
}
