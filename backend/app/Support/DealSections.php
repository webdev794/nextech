<?php

namespace App\Support;

use App\Models\Product;
use App\Models\Setting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * The three deal sections, filled automatically per country (no manual picks):
 *  - Lightning deals: products their seller (or admin) put on a lightning deal
 *    — a time window and a quantity, discounted at least `lightning_min_pct` —
 *    soonest-ending first; topped up with the best sellers (most bought)
 *    when fewer deals are running, up to `lightning_max`.
 *  - Unbeatable deals: the biggest discounts (at least `unbeatable_min_pct`),
 *    taken in turn from every category so each gets its own, up to `unbeatable_max`.
 *  - Exclusive offers: the lowest-priced products sold in that country's own
 *    currency — at least `exclusive_min` — shown as "Under $X" / "Under ₹X".
 * A product appears in only one section. Ads are never included; demo products
 * follow the demo show/hide switch like everywhere else.
 */
class DealSections
{
    public const DEFAULTS = [
        'lightning_hours' => 12, 'lightning_min_pct' => 20, 'lightning_max' => 20,
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
        return $p->lightning_starts_at && $p->lightning_ends_at && $p->lightning_qty
            && $p->lightning_starts_at->isPast() && $p->lightning_ends_at->isFuture()
            && self::lightningClaimed($p) < (int) $p->lightning_qty;
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
            ->get(['id', 'market', 'category_id', 'price_cents', 'compare_at_price_cents', 'units_sold', 'lightning_starts_at', 'lightning_ends_at', 'lightning_qty', 'lightning_base_sold']);

        // A lightning deal that has just begun counts its sales from now.
        $all->filter(fn (Product $p) => $p->lightning_starts_at?->isPast() && $p->lightning_ends_at?->isFuture() && $p->lightning_base_sold === null)
            ->each(fn (Product $p) => $p->forceFill(['lightning_base_sold' => (int) $p->units_sold])->saveQuietly());

        $lightning = $all->filter(fn (Product $p) => self::lightningLive($p) && self::discountPct($p) >= $r['lightning_min_pct'])
            ->sortBy(fn (Product $p) => $p->lightning_ends_at->timestamp)->pluck('id');
        if ($lightning->count() < $r['lightning_max']) {
            // Few deals running: the most bought products fill the section.
            $lightning = $lightning->merge($all->where('units_sold', '>', 0)->whereNotIn('id', $lightning)->sortByDesc('units_sold')->pluck('id'));
        }
        $lightning = $lightning->take($r['lightning_max'])->values();

        // Biggest discounts, one category at a time so every category gets its own.
        $byCategory = $all->whereNotIn('id', $lightning)->filter(fn (Product $p) => self::discountPct($p) >= $r['unbeatable_min_pct'])
            ->sortByDesc(fn (Product $p) => self::discountPct($p))->groupBy('category_id')->map->values();
        $unbeatable = collect();
        for ($round = 0; $unbeatable->count() < $r['unbeatable_max'] && $byCategory->contains(fn ($g) => $g->has($round)); $round++) {
            foreach ($byCategory as $group) {
                if ($group->has($round) && $unbeatable->count() < $r['unbeatable_max']) {
                    $unbeatable->push($group[$round]->id);
                }
            }
        }

        // Cheapest products sold in this country's own currency; the cap is the n-th cheapest's price.
        $own = $all->where('market', $market)->whereNotIn('id', $lightning->merge($unbeatable))->sortBy('price_cents')->values();
        $under = null;
        $exclusive = collect();
        if ($own->isNotEmpty()) {
            $nth = $own->get(min($r['exclusive_min'], $own->count()) - 1);
            $under = self::niceCap((int) $nth->price_cents, $market);
            $exclusive = $own->filter(fn (Product $p) => (int) $p->price_cents <= $under)->take(max($r['exclusive_max'], $r['exclusive_min']))->pluck('id');
        }

        return [
            'lightning' => $lightning->all(),
            'unbeatable' => $unbeatable->all(),
            'exclusive' => $exclusive->values()->all(),
            'under_cents' => $under,
            'currency' => Market::currency($market),
        ];
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
