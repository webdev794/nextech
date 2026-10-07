<?php

namespace App\Support;

use App\Models\Product;
use App\Models\Setting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * The three deal sections, filled automatically per country (no manual picks):
 *  - Lightning deals: products their seller (or admin) put on a lightning deal
 *    (time window, units, % off), soonest-ending first; topped up with the
 *    best sellers (most bought), up to `lightning_max`.
 *  - Unbeatable deals: the biggest discounts — at least `unbeatable_min_pct`
 *    first, then the next-biggest when there are too few — taken in turn from
 *    every category so each gets its own, up to `unbeatable_max`.
 *  - Exclusive offers: the lowest-priced products sold in that country's own
 *    currency — at least `exclusive_min` — shown as "Under $X" / "Under ₹X".
 * Products are shared out evenly (about a third each) so no section is empty,
 * and a product appears in only one section. Ads are never included; demo products
 * follow the demo show/hide switch like everywhere else.
 */
class DealSections
{
    public const DEFAULTS = [
        'lightning_hours' => 12, 'lightning_min_pct' => 20, 'lightning_max' => 20, 'lightning_max_share' => 40,
        'unbeatable_min_pct' => 30, 'unbeatable_max' => 40,
        'exclusive_min' => 12, 'exclusive_max' => 60,
    ];

    /** @return array<string, int> */
    public static function settings(): array
    {
        $saved = (array) Setting::get('deal_rules', []);

        return collect(self::DEFAULTS)->map(fn ($v, $k) => max(1, (int) ($saved[$k] ?? $v)))->all();
    }

    /** Percent off: compare-at price vs price (0 when not discounted). */
    public static function discountPct(Product $p): int
    {
        $compare = (int) $p->compare_at_price_cents;
        $price = (int) $p->price_cents;

        return $compare > $price && $compare > 0 ? (int) floor(($compare - $price) * 100 / $compare) : 0;
    }

    /** Is this product's lightning deal running now (window open, units left)? */
    public static function lightningLive(Product $p): bool
    {
        return $p->lightning_starts_at && $p->lightning_ends_at && $p->lightning_qty && $p->lightning_pct
            && $p->lightning_starts_at->isPast() && $p->lightning_ends_at->isFuture()
            && self::lightningClaimed($p) < (int) $p->lightning_qty;
    }

    /** A price during a running lightning deal ($cents off by the deal's %), else unchanged. */
    public static function lightningPrice(Product $p, int $cents): int
    {
        return self::lightningLive($p) ? (int) round($cents * (100 - (int) $p->lightning_pct) / 100) : $cents;
    }

    public static function lightningClaimed(Product $p): int
    {
        return max(0, (int) $p->units_sold - (int) ($p->lightning_base_sold ?? $p->units_sold));
    }

    /**
     * The ids in each section for a country, in display order (cached briefly).
     *
     * @return array{lightning: list<int>, unbeatable: list<int>, exclusive: list<int>, under_cents: ?int, currency: string}
     */
    public static function forMarket(string $market): array
    {
        return Cache::remember('deal-sections:'.strtoupper($market).':'.(int) Product::demosHidden(), 300, fn () => self::build(strtoupper($market)));
    }

    public static function forget(): void
    {
        foreach (Market::codes() as $m) {
            Cache::forget("deal-sections:{$m}:0");
            Cache::forget("deal-sections:{$m}:1");
        }
    }

    private static function build(string $market): array
    {
        $r = self::settings();
        /** @var Collection<int, Product> $all */
        $all = Product::query()
            ->where('is_active', true)->where('status', 'approved')->whereNull('affiliate_url')
            ->shownToShoppers()->availableIn($market)
            ->whereHas('category', fn ($q) => $q->where('is_active', true))
            ->get(['id', 'market', 'category_id', 'price_cents', 'compare_at_price_cents', 'units_sold', 'lightning_starts_at', 'lightning_ends_at', 'lightning_qty', 'lightning_base_sold', 'lightning_pct']);

        // A lightning deal that has just begun counts its sales from now.
        $all->filter(fn (Product $p) => $p->lightning_starts_at?->isPast() && $p->lightning_ends_at?->isFuture() && $p->lightning_base_sold === null)
            ->each(fn (Product $p) => $p->forceFill(['lightning_base_sold' => (int) $p->units_sold])->saveQuietly());

        // Share products out evenly so no section is empty or lopsided: each aims for a third of what's available.
        $third = max(1, intdiv($all->count(), 3));

        // Time-limited offers come first, soonest-ending first; best sellers (most bought) fill up to the target.
        $lightning = $all->filter(fn (Product $p) => self::lightningLive($p))
            ->sortBy(fn (Product $p) => $p->lightning_ends_at->timestamp)->pluck('id');
        $lightningTarget = min($r['lightning_max'], max($lightning->count(), $third));
        if ($lightning->count() < $lightningTarget) {
            $lightning = $lightning->merge($all->whereNotIn('id', $lightning)->sortByDesc('units_sold')->pluck('id'));
        }
        $lightning = $lightning->take($lightningTarget)->values();

        // Biggest discounts, one category at a time so every category gets its own. Those at the
        // minimum % off go first; when there are too few, the next-biggest discounts fill it up.
        $rest = $all->whereNotIn('id', $lightning);
        $strong = $rest->filter(fn (Product $p) => self::discountPct($p) >= $r['unbeatable_min_pct']);
        $unbeatable = self::roundRobin($strong, $r['unbeatable_max']);
        // Half of what's left (the other half goes to Exclusive offers), so both stay filled.
        $unbeatableTarget = min($r['unbeatable_max'], max(1, intdiv($rest->count(), 2)));
        if ($unbeatable->count() < $unbeatableTarget) {
            $unbeatable = $unbeatable->merge(self::roundRobin($rest->whereNotIn('id', $unbeatable), $unbeatableTarget - $unbeatable->count()));
        }

        // Cheapest products sold in this country's own currency, at least `exclusive_min`; the cap is the last one's price.
        $own = $all->where('market', $market)->whereNotIn('id', $lightning->merge($unbeatable))->sortBy('price_cents')->values();
        $under = null;
        $exclusive = collect();
        if ($own->isNotEmpty()) {
            $count = min($own->count(), max($r['exclusive_min'], min($third, $r['exclusive_max'])));
            $under = self::niceCap((int) $own->get($count - 1)->price_cents, $market);
            $exclusive = $own->filter(fn (Product $p) => (int) $p->price_cents <= $under)->take(max($r['exclusive_max'], $count))->pluck('id');
        }

        return [
            'lightning' => $lightning->all(),
            'unbeatable' => $unbeatable->all(),
            'exclusive' => $exclusive->values()->all(),
            'under_cents' => $under,
            'currency' => Market::currency($market),
        ];
    }

    /**
     * Up to $limit products, biggest discount first, taken in turn from each category.
     *
     * @param  Collection<int, Product>  $products
     * @return Collection<int, int>
     */
    private static function roundRobin(Collection $products, int $limit): Collection
    {
        $byCategory = $products->sortByDesc(fn (Product $p) => self::discountPct($p) * 1000000 + (int) $p->units_sold)->groupBy('category_id')->map->values();
        $out = collect();
        for ($round = 0; $out->count() < $limit && $byCategory->contains(fn ($g) => $g->has($round)); $round++) {
            foreach ($byCategory as $group) {
                if ($group->has($round) && $out->count() < $limit) {
                    $out->push($group[$round]->id);
                }
            }
        }

        return $out;
    }

    /** Round a price up to a tidy "Under …" figure ($5 / $10 / $25 / $50… ; ₹100 / ₹500 / ₹1,000…). */
    private static function niceCap(int $cents, string $market): int
    {
        $steps = Market::currency($market) === 'inr'
            ? [10000, 20000, 50000, 100000, 200000, 500000, 1000000, 2000000, 5000000]
            : [500, 1000, 2000, 2500, 5000, 10000, 20000, 25000, 50000, 100000];
        foreach ($steps as $step) {
            if ($cents <= $step) {
                return $step;
            }
        }
        $top = end($steps);

        return (int) (ceil($cents / $top) * $top);
    }
}
