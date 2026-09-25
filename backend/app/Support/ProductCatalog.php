<?php

namespace App\Support;

use App\Models\Category;
use App\Models\Product;

/**
 * The rules behind a seller's product listing (config/product_catalog.php):
 * which product details a category asks for, how products vary, which
 * compliance documents a market needs, and what a listing must contain before
 * it can be submitted for review. Shared by the Add product wizard and the
 * spreadsheet upload so both check listings the same way.
 */
class ProductCatalog
{
    /** @return list<array<string, mixed>> */
    public static function attributesFor(?Category $category): array
    {
        $extra = $category ? (array) config('product_catalog.category_attributes.'.$category->slug, []) : [];
        $extraKeys = array_column($extra, 'key');

        // A category's own definition of a field replaces the common one.
        return [...$extra, ...array_values(array_filter((array) config('product_catalog.common_attributes'), fn ($f) => ! in_array($f['key'], $extraKeys, true)))];
    }

    public static function isApparel(?Category $category): bool
    {
        return $category !== null && in_array($category->slug, (array) config('product_catalog.apparel_categories'), true);
    }

    /** @return list<array<string, mixed>> */
    public static function complianceFor(?string $market, ?Category $category): array
    {
        $market = strtoupper((string) ($market ?? Market::home()));
        $byMarket = (array) config('product_catalog.compliance.'.$market, config('product_catalog.compliance.US'));
        $specific = $category ? (array) ($byMarket[$category->slug] ?? []) : [];
        $keys = array_column($specific, 'key');

        return [...$specific, ...array_values(array_filter((array) ($byMarket['default'] ?? []), fn ($d) => ! in_array($d['key'], $keys, true)))];
    }

    /**
     * Whether a conditional field (or document) applies, given the product's
     * details: every `when` key must hold one of the listed values.
     *
     * @param  array<string, mixed>  $field
     * @param  array<string, mixed>  $attributes
     */
    public static function applies(array $field, array $attributes): bool
    {
        foreach ((array) ($field['when'] ?? []) as $key => $values) {
            $have = (array) ($attributes[$key] ?? []);
            if (! array_intersect(array_map('strval', $have), array_map('strval', (array) $values))) {
                return false;
            }
        }

        return true;
    }

    /**
     * Required compliance documents the product still lacks (labels).
     *
     * @return list<string>
     */
    public static function missingCompliance(Product $product): array
    {
        $product->loadMissing('category');
        $have = collect((array) ($product->compliance['documents'] ?? []))->pluck('type')->all();
        $attributes = (array) $product->product_details;

        return collect(self::complianceFor($product->market, $product->category))
            ->filter(fn ($d) => ($d['required'] ?? false) && self::applies($d, $attributes) && ! in_array($d['key'], $have, true))
            ->pluck('label')->values()->all();
    }

    /**
     * What a listing still needs before it can be submitted for review.
     * Empty = ready. Keys are form fields so both the wizard and the upload
     * report can point at the right place.
     *
     * @param  array<string, mixed>  $data  the product fields (as stored)
     * @param  list<array<string, mixed>>  $variants  the live variant rows
     * @param  list<string>  $images
     * @return array<string, string>
     */
    public static function listingErrors(array $data, ?Category $category, array $variants, array $images, bool $shipsItself): array
    {
        $errors = [];
        if (! $category) {
            $errors['category_id'] = 'Choose a category.';
        }
        if (trim((string) ($data['name'] ?? '')) === '') {
            $errors['name'] = 'Enter a product name.';
        }
        if (trim((string) ($data['description'] ?? '')) === '') {
            $errors['description'] = 'Add a product description.';
        }
        if (! $images) {
            $errors['images'] = 'Add at least one product image.';
        }
        if (! $variants && (int) ($data['price_cents'] ?? 0) <= 0) {
            $errors['price_cents'] = 'Enter the base price.';
        }
        if (trim((string) ($data['country_of_origin'] ?? '')) === '') {
            $errors['country_of_origin'] = 'Choose the country/region of origin.';
        }
        if ($shipsItself && empty($data['handling_days'])) {
            $errors['handling_days'] = 'Choose a handling time.';
        }

        $attributes = (array) ($data['product_details'] ?? []);
        foreach (self::attributesFor($category) as $field) {
            $value = $attributes[$field['key']] ?? null;
            $empty = $value === null || $value === '' || $value === [];
            if (($field['required'] ?? false) && self::applies($field, $attributes) && $empty) {
                $errors['product_details.'.$field['key']] = $field['label'].' is required.';
            } elseif (! $empty && in_array($field['type'], ['select', 'multiselect'], true)) {
                $bad = array_diff(array_map('strval', (array) $value), array_map('strval', $field['options']));
                if ($bad) {
                    $errors['product_details.'.$field['key']] = $field['label'].': "'.reset($bad).'" isn’t one of the options.';
                }
            } elseif (! $empty && $field['type'] === 'number' && ! is_numeric($value)) {
                $errors['product_details.'.$field['key']] = $field['label'].' must be a number.';
            }
        }

        $theme = array_values((array) ($data['variation_theme'] ?? []));
        $apparel = self::isApparel($category);
        if ($variants) {
            if (! $theme) {
                $errors['variation_theme'] = 'Choose what the variations differ by.';
            } elseif (count($theme) > (int) config('product_catalog.max_variation_levels', 2)) {
                $errors['variation_theme'] = 'Products can vary in at most two ways.';
            } elseif ($apparel && $theme !== ['Color', 'Size']) {
                $errors['variation_theme'] = 'Clothing varies by Color × Size only.';
            } elseif (array_diff($theme, (array) config('product_catalog.variation_types'))) {
                $errors['variation_theme'] = 'Choose variation types from the list.';
            } else {
                $seen = [];
                foreach ($variants as $i => $variant) {
                    $options = (array) ($variant['options'] ?? []);
                    foreach ($theme as $type) {
                        if (trim((string) ($options[$type] ?? '')) === '') {
                            $errors["variants.$i.options"] = 'Every SKU needs a '.strtolower($type).'.';
                        }
                    }
                    $key = implode('|', array_map(fn ($t) => mb_strtolower(trim((string) ($options[$t] ?? ''))), $theme));
                    if (isset($seen[$key])) {
                        $errors["variants.$i.options"] = 'Two SKUs have the same '.implode(' and ', array_map('strtolower', $theme)).'.';
                    }
                    $seen[$key] = true;
                    if ((int) ($variant['price_cents'] ?? 0) <= 0) {
                        $errors["variants.$i.price_cents"] = 'Enter a base price for every SKU.';
                    }
                }
            }
        }
        if ($apparel) {
            $sizes = collect($variants)->pluck('options.Size')->filter()->unique()->values()->all();
            $chart = (array) ($data['size_chart']['rows'] ?? []);
            $charted = collect($chart)->pluck('size')->all();
            if (! $sizes || array_diff($sizes, $charted)) {
                $errors['size_chart'] = 'Clothing needs a size chart with a row for every size.';
            }
        }

        return $errors;
    }

    /**
     * Everything the seller's listing forms need to know.
     *
     * @return array<string, mixed>
     */
    public static function clientConfig(?string $market): array
    {
        $categories = Category::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'slug']);

        return [
            'categories' => $categories->map(fn (Category $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'slug' => $c->slug,
                'apparel' => self::isApparel($c),
                'attributes' => self::attributesFor($c),
                'compliance' => self::complianceFor($market, $c),
                'keywords' => (array) config('product_catalog.keywords.'.$c->slug, []),
            ])->values(),
            'variation_types' => config('product_catalog.variation_types'),
            'max_variation_levels' => config('product_catalog.max_variation_levels'),
            'size_families' => config('product_catalog.size_families'),
            'sub_size_families' => config('product_catalog.sub_size_families'),
            'size_chart_measurements' => config('product_catalog.size_chart_measurements'),
            'handling_days' => config('product_catalog.handling_days'),
            'max_images' => ProductImages::MAX_IMAGES,
            'max_skus' => Sku::MAX_VARIANTS,
        ];
    }
}
