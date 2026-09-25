<?php

namespace App\Support;

use App\Models\Category;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Shop;
use App\Models\StoreDecoration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Store decoration rules, modelled on Temu's store page designer: which
 * sections a design can use, what each needs before it can be submitted,
 * and how a live design is turned into what shoppers see.
 *
 * Temu's sections: background image, banner, category, product and video.
 * NexTech adds: announcement bar, image grid, automatic product lists,
 * sale countdown, brand story and spacer, plus page theme colours.
 */
class StoreDecorations
{
    public const PLATFORMS = ['desktop', 'mobile'];

    public const MAX_VERSIONS = 10;

    public const MAX_SECTIONS = 30;

    public const TYPES = ['banner', 'category', 'products', 'video', 'announcement', 'image_grid', 'auto_products', 'countdown', 'brand_story', 'spacer'];

    public const AUTO_SOURCES = ['best_selling', 'newest', 'top_rated', 'on_sale'];

    /** Shops need this many live products before a decorated page is shown (else the default page). */
    public static function minProducts(): int
    {
        return (int) Setting::get('decoration_min_products', 30);
    }

    /** Share of submitted versions NexTech spot-checks before they can be published. */
    public static function spotCheckRate(): float
    {
        return (float) Setting::get('decoration_spot_check_rate', 0.3);
    }

    /** Recommended image sizes (px) per platform, shown in the editor and checked there. */
    public static function imageSpecs(): array
    {
        return [
            'desktop' => ['background' => [1920, 400], 'banner' => [1920, 600], 'tile' => [600, 600], 'category' => [300, 300], 'story' => [800, 600], 'poster' => [1280, 720]],
            'mobile' => ['background' => [750, 400], 'banner' => [750, 750], 'tile' => [350, 350], 'category' => [200, 200], 'story' => [750, 560], 'poster' => [750, 422]],
        ];
    }

    private static function str(mixed $value, int $max): string
    {
        return Str::limit(trim((string) $value), $max, '');
    }

    private static function color(mixed $value, string $fallback): string
    {
        return is_string($value) && preg_match('/^#[0-9a-fA-F]{6}$/', $value) ? $value : $fallback;
    }

    /** @return array{type: string, value: mixed}|null */
    private static function link(mixed $link): ?array
    {
        $type = $link['type'] ?? 'none';

        return match ($type) {
            'product' => ['type' => 'product', 'value' => (int) ($link['value'] ?? 0)],
            'category' => ['type' => 'category', 'value' => (int) ($link['value'] ?? 0)],
            default => null,
        };
    }

    /**
     * Keep only the fields each section type uses, trimmed to size.
     *
     * @param  array<int, mixed>  $sections
     * @return list<array<string, mixed>>
     */
    public static function clean(array $sections): array
    {
        $out = [];
        foreach (array_slice($sections, 0, self::MAX_SECTIONS) as $s) {
            $type = $s['type'] ?? null;
            if (! in_array($type, self::TYPES, true)) {
                continue;
            }
            $base = ['id' => self::str($s['id'] ?? Str::random(8), 20), 'type' => $type, 'title' => self::str($s['title'] ?? '', 80)];
            $ids = fn ($list, $max) => array_values(array_unique(array_map('intval', array_slice((array) $list, 0, $max))));
            $out[] = $base + match ($type) {
                'banner' => ['slides' => array_map(fn ($sl) => ['image_url' => self::str($sl['image_url'] ?? '', 500), 'link' => self::link($sl['link'] ?? null)], array_slice((array) ($s['slides'] ?? []), 0, 6)), 'autoplay' => (bool) ($s['autoplay'] ?? true)],
                'category' => ['items' => array_map(fn ($it) => ['category_id' => (int) ($it['category_id'] ?? 0), 'image_url' => self::str($it['image_url'] ?? '', 500)], array_slice((array) ($s['items'] ?? []), 0, 12))],
                'products' => ['product_ids' => $ids($s['product_ids'] ?? [], 20), 'layout' => ($s['layout'] ?? 'grid') === 'carousel' ? 'carousel' : 'grid'],
                'video' => ['video_url' => self::str($s['video_url'] ?? '', 500), 'poster_url' => self::str($s['poster_url'] ?? '', 500), 'caption' => self::str($s['caption'] ?? '', 200)],
                'announcement' => ['text' => self::str($s['text'] ?? '', 160), 'bg_color' => self::color($s['bg_color'] ?? null, '#1f2328'), 'text_color' => self::color($s['text_color'] ?? null, '#ffffff'), 'link' => self::link($s['link'] ?? null)],
                'image_grid' => ['columns' => in_array((int) ($s['columns'] ?? 2), [2, 3, 4], true) ? (int) $s['columns'] : 2, 'tiles' => array_map(fn ($t) => ['image_url' => self::str($t['image_url'] ?? '', 500), 'link' => self::link($t['link'] ?? null)], array_slice((array) ($s['tiles'] ?? []), 0, 8))],
                'auto_products' => ['source' => in_array($s['source'] ?? '', self::AUTO_SOURCES, true) ? $s['source'] : 'best_selling', 'limit' => max(4, min(20, (int) ($s['limit'] ?? 8)))],
                'countdown' => ['ends_at' => self::str($s['ends_at'] ?? '', 40), 'subtitle' => self::str($s['subtitle'] ?? '', 160), 'bg_color' => self::color($s['bg_color'] ?? null, '#e5432c'), 'product_ids' => $ids($s['product_ids'] ?? [], 12)],
                'brand_story' => ['text' => self::str($s['text'] ?? '', 1500), 'image_url' => self::str($s['image_url'] ?? '', 500), 'image_side' => ($s['image_side'] ?? 'left') === 'right' ? 'right' : 'left'],
                'spacer' => ['size' => in_array($s['size'] ?? '', ['small', 'medium', 'large'], true) ? $s['size'] : 'medium'],
            };
        }

        return $out;
    }

    /** @param  array<string, mixed>|null  $page */
    public static function cleanPage(?array $page): array
    {
        return [
            'background_image_url' => self::str($page['background_image_url'] ?? '', 500),
            'background_color' => self::color($page['background_color'] ?? null, '#f3f4f6'),
            'accent_color' => self::color($page['accent_color'] ?? null, '#fb7701'),
        ];
    }

    /**
     * What a version still needs before it can be submitted ("red pop-up").
     *
     * @return list<string>
     */
    public static function problems(StoreDecoration $decoration, Shop $shop): array
    {
        $sections = (array) $decoration->sections;
        $problems = [];
        if (! $sections) {
            $problems[] = 'Add at least one section to the canvas.';
        }
        $liveIds = self::liveProducts($shop)->pluck('id')->all();
        $shopCategories = self::shopCategoryIds($shop);
        $labels = ['banner' => 'Banner', 'category' => 'Category section', 'products' => 'Product section', 'video' => 'Video section', 'announcement' => 'Announcement bar', 'image_grid' => 'Image grid', 'auto_products' => 'Auto product list', 'countdown' => 'Countdown', 'brand_story' => 'Brand story', 'spacer' => 'Spacer'];
        foreach ($sections as $i => $s) {
            $at = ($labels[$s['type']] ?? $s['type']).' (section '.($i + 1).')';
            $checkLinks = function (array $items) use ($at, $liveIds, $shopCategories, &$problems) {
                foreach ($items as $it) {
                    $link = $it['link'] ?? null;
                    if ($link && $link['type'] === 'product' && ! in_array($link['value'], $liveIds, true)) {
                        $problems[] = "$at: a link points to a product that isn’t live in your store.";
                    }
                    if ($link && $link['type'] === 'category' && ! in_array($link['value'], $shopCategories, true)) {
                        $problems[] = "$at: a link points to a category your store doesn’t sell in.";
                    }
                }
            };
            switch ($s['type']) {
                case 'banner':
                    if (! array_filter(array_column($s['slides'], 'image_url'))) {
                        $problems[] = "$at: add at least one banner image.";
                    }
                    if (count(array_filter(array_column($s['slides'], 'image_url'))) !== count($s['slides'])) {
                        $problems[] = "$at: every slide needs an image.";
                    }
                    $checkLinks($s['slides']);
                    break;
                case 'category':
                    if (! $s['items']) {
                        $problems[] = "$at: choose at least one category.";
                    }
                    if (array_diff(array_column($s['items'], 'category_id'), $shopCategories)) {
                        $problems[] = "$at: only categories your store sells in can be shown.";
                    }
                    break;
                case 'products':
                case 'countdown':
                    if ($s['type'] === 'products' && ! $s['product_ids']) {
                        $problems[] = "$at: choose at least one product.";
                    }
                    if (array_diff($s['product_ids'], $liveIds)) {
                        $problems[] = "$at: some chosen products aren’t live in your store.";
                    }
                    if ($s['type'] === 'countdown') {
                        $ends = rescue(fn () => Carbon::parse($s['ends_at']), null, false);
                        if (! $ends || $ends->isPast()) {
                            $problems[] = "$at: set an end date and time in the future.";
                        }
                        if ($s['title'] === '') {
                            $problems[] = "$at: give the sale event a title.";
                        }
                    }
                    break;
                case 'video':
                    if ($s['video_url'] === '') {
                        $problems[] = "$at: upload a video.";
                    }
                    break;
                case 'announcement':
                    if ($s['text'] === '') {
                        $problems[] = "$at: write the announcement text.";
                    }
                    $checkLinks([$s]);
                    break;
                case 'image_grid':
                    if (count($s['tiles']) < 2 || count(array_filter(array_column($s['tiles'], 'image_url'))) !== count($s['tiles'])) {
                        $problems[] = "$at: add at least two tiles, each with an image.";
                    }
                    $checkLinks($s['tiles']);
                    break;
                case 'brand_story':
                    if ($s['text'] === '' || $s['image_url'] === '') {
                        $problems[] = "$at: add both an image and your story text.";
                    }
                    break;
            }
        }

        return array_values(array_unique($problems));
    }

    /** @return Collection<int, Product> */
    public static function liveProducts(Shop $shop): Collection
    {
        return $shop->products()->where('is_active', true)->where('status', 'approved')->get(['id', 'name', 'slug', 'image_url', 'price_cents', 'compare_at_price_cents', 'category_id', 'rating_avg', 'rating_count', 'units_sold', 'created_at']);
    }

    /** @return list<int> */
    public static function shopCategoryIds(Shop $shop): array
    {
        return $shop->products()->where('is_active', true)->where('status', 'approved')->whereNotNull('category_id')->distinct()->pluck('category_id')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * The design as shoppers get it: product and category details filled in,
     * automatic lists worked out, expired countdowns and dead links dropped.
     *
     * @return array{page: array<string, mixed>, sections: list<array<string, mixed>>}
     */
    public static function resolve(StoreDecoration $decoration, Shop $shop): array
    {
        $products = self::liveProducts($shop)->keyBy('id');
        $card = fn (Product $p) => ['id' => $p->id, 'name' => $p->name, 'slug' => $p->slug, 'image_url' => $p->image_url, 'price_cents' => $p->price_cents, 'compare_at_price_cents' => $p->compare_at_price_cents, 'rating_avg' => $p->rating_avg, 'rating_count' => $p->rating_count];
        $categories = Category::whereIn('id', self::shopCategoryIds($shop))->get(['id', 'name', 'slug', 'image_url'])->keyBy('id');
        $link = function (?array $l) use ($products, $categories) {
            if (! $l) {
                return null;
            }
            if ($l['type'] === 'product' && $products->has($l['value'])) {
                return ['type' => 'product', 'slug' => $products[$l['value']]->slug];
            }
            if ($l['type'] === 'category' && $categories->has($l['value'])) {
                return ['type' => 'category', 'name' => $categories[$l['value']]->name];
            }

            return null;
        };

        $sections = [];
        foreach ((array) $decoration->sections as $s) {
            switch ($s['type']) {
                case 'banner':
                    $s['slides'] = array_map(fn ($sl) => ['image_url' => $sl['image_url'], 'link' => $link($sl['link'])], $s['slides']);
                    break;
                case 'image_grid':
                    $s['tiles'] = array_map(fn ($t) => ['image_url' => $t['image_url'], 'link' => $link($t['link'])], $s['tiles']);
                    break;
                case 'announcement':
                    $s['link'] = $link($s['link']);
                    break;
                case 'category':
                    $s['items'] = collect($s['items'])->filter(fn ($it) => $categories->has($it['category_id']))
                        ->map(fn ($it) => ['name' => $categories[$it['category_id']]->name, 'slug' => $categories[$it['category_id']]->slug, 'image_url' => $it['image_url'] ?: $categories[$it['category_id']]->image_url])->values()->all();
                    break;
                case 'products':
                case 'countdown':
                    $s['products'] = collect($s['product_ids'])->filter(fn ($id) => $products->has($id))->map(fn ($id) => $card($products[$id]))->values()->all();
                    unset($s['product_ids']);
                    if ($s['type'] === 'countdown' && rescue(fn () => Carbon::parse($s['ends_at'])->isPast(), true, false)) {
                        continue 2; // the sale is over
                    }
                    break;
                case 'auto_products':
                    $sorted = match ($s['source']) {
                        'newest' => $products->sortByDesc('created_at'),
                        'top_rated' => $products->sortByDesc(fn ($p) => [$p->rating_avg, $p->rating_count]),
                        'on_sale' => $products->filter(fn ($p) => $p->compare_at_price_cents > $p->price_cents),
                        default => $products->sortByDesc('units_sold'),
                    };
                    $s['products'] = $sorted->take($s['limit'])->map($card)->values()->all();
                    break;
            }
            $sections[] = $s;
        }

        return ['page' => self::cleanPage($decoration->page), 'sections' => $sections];
    }
}
