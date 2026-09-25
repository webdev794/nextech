<?php

namespace App\Support;

use App\Models\Category;
use App\Models\Product;
use App\Models\Shop;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Products -> Add products via upload. The seller fills in the template the
 * browser generated (one row per SKU; rows sharing a "Contribution Goods"
 * code are one product) and uploads it; the browser reads the sheet into
 * rows keyed by the template's column keys and this turns them into
 * products. Complete products are submitted for review; ones with errors
 * are saved as drafts (Manage products -> Incomplete) and the result for
 * every row says what to fix.
 */
class ProductUpload
{
    public const MAX_CATEGORIES = 5;

    public const MAX_ROWS = 500;

    /** Columns that describe the product and must match on every row (SKU) of it. */
    private const PRODUCT_COLUMNS = [
        'category', 'product_name', 'brand', 'description', 'variation_theme', 'handling_time', 'shipping_template',
        'country_of_origin', 'province_of_origin', 'product_video_url', 'detail_video_url', 'hsn_code', 'gst_rate', 'manufacturer_info',
    ];

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<int>  $categoryIds  the categories the template was generated for
     * @return array{results: list<array<string, mixed>>, error_records: int}
     */
    public static function process(Shop $shop, array $rows, array $categoryIds): array
    {
        $categories = Category::whereIn('id', $categoryIds)->get()->keyBy(fn ($c) => mb_strtolower($c->name));
        $trademarks = $shop->trademarks()->where('status', 'approved')->get()->keyBy(fn ($t) => mb_strtolower($t->name));
        $templates = $shop->shippingTemplates()->get()->keyBy(fn ($t) => mb_strtolower($t->name));
        $types = (array) config('product_catalog.variation_types');

        $groups = [];
        foreach ($rows as $i => $row) {
            $row = array_map(fn ($v) => is_string($v) ? trim($v) : $v, (array) $row);
            if (! array_filter($row, fn ($v) => $v !== null && $v !== '')) {
                continue; // blank line
            }
            $code = (string) ($row['contribution_goods'] ?? '');
            $groups[$code !== '' ? 'g:'.mb_strtolower($code) : 'r:'.$i][] = ['index' => $i, 'row' => $row];
        }

        $results = [];
        $errorRows = 0;
        foreach ($groups as $group) {
            $first = $group[0]['row'];
            $messages = [];

            foreach (self::PRODUCT_COLUMNS as $column) {
                $values = collect($group)->map(fn ($g) => (string) ($g['row'][$column] ?? ''))->unique();
                if ($values->count() > 1) {
                    $messages[] = self::label($column).' must be the same on every SKU of the product.';
                }
            }

            $category = $categories->get(mb_strtolower((string) ($first['category'] ?? '')));
            if (! $category) {
                $messages[] = ($first['category'] ?? '') === ''
                    ? 'Choose a category.'
                    : '"'.$first['category'].'" isn’t one of the categories this template was made for — generate a new template to add it.';
            }

            $trademarkId = null;
            if (($first['brand'] ?? '') !== '') {
                $trademarkId = $trademarks->get(mb_strtolower($first['brand']))?->id;
                if (! $trademarkId) {
                    $messages[] = 'Brand "'.$first['brand'].'" isn’t an approved trademark — register it under Account health first.';
                }
            }

            $templateId = null;
            if (($first['shipping_template'] ?? '') !== '') {
                $templateId = $templates->get(mb_strtolower($first['shipping_template']))?->id;
                if (! $templateId) {
                    $messages[] = 'No shipping template is called "'.$first['shipping_template'].'".';
                }
            }

            $theme = array_values(array_filter(array_map('trim', preg_split('/\s*(?:×|x|X|\*|\+)\s*/u', (string) ($first['variation_theme'] ?? '')) ?: [])));
            if (array_diff($theme, $types)) {
                $messages[] = 'Variation theme "'.$first['variation_theme'].'" isn’t one of: '.implode(', ', $types).'.';
                $theme = array_values(array_intersect($theme, $types));
            }
            if (! $theme && count($group) > 1) {
                $messages[] = 'Several SKUs share this Contribution Goods code — choose a variation theme.';
            }

            $details = [];
            foreach (ProductCatalog::attributesFor($category) as $field) {
                $value = $first['detail_'.$field['key']] ?? null;
                if ($value === null || $value === '') {
                    continue;
                }
                $details[$field['key']] = $field['type'] === 'multiselect'
                    ? array_values(array_filter(array_map('trim', explode(';', (string) $value))))
                    : (string) $value;
            }

            $urls = fn (array $row, string $prefix, int $max) => collect(range(1, $max))->map(fn ($n) => (string) ($row[$prefix.$n] ?? ''))->filter()->values();
            $badUrls = collect($group)->flatMap(fn ($g) => [
                ...$urls($g['row'], 'sku_image_url_', 10), ...$urls($g['row'], 'detail_image_url_', 5),
                $g['row']['product_video_url'] ?? '', $g['row']['detail_video_url'] ?? '', $g['row']['price_reference_url'] ?? '',
            ])->filter()->reject(fn ($u) => preg_match('#^https?://\S+$#i', $u))->unique();
            foreach ($badUrls as $bad) {
                $messages[] = '"'.Str::limit($bad, 60).'" isn’t a public web address (it must start with http:// or https://).';
            }

            $variants = [];
            if ($theme) {
                foreach ($group as $n => $g) {
                    $options = [];
                    foreach ($theme as $t => $type) {
                        $options[$type] = (string) ($g['row']['variation_value_'.($t + 1)] ?? '');
                    }
                    $variants[] = [
                        'label' => implode(' / ', array_filter($options)) ?: 'Option '.($n + 1),
                        'options' => $options,
                        'seller_code' => ($g['row']['contribution_sku'] ?? '') ?: null,
                        'price_cents' => self::cents($g['row']['base_price'] ?? null),
                        'inventory_quantity' => (int) ($g['row']['quantity'] ?? 0),
                        'image_url' => $urls($g['row'], 'sku_image_url_', 10)->first(),
                        'weight_grams' => self::int($g['row']['weight_g'] ?? null),
                        'length_mm' => self::int($g['row']['length_mm'] ?? null),
                        'width_mm' => self::int($g['row']['width_mm'] ?? null),
                        'height_mm' => self::int($g['row']['height_mm'] ?? null),
                    ];
                }
            }

            $images = $urls($first, 'sku_image_url_', 10)->all();
            $handling = self::int($first['handling_time'] ?? null);
            if ($handling !== null && ! in_array($handling, (array) config('product_catalog.handling_days'), true)) {
                $messages[] = 'Handling time must be one of: '.implode(', ', (array) config('product_catalog.handling_days')).' days.';
                $handling = null;
            }
            $data = [
                'category_id' => $category?->id,
                'name' => Str::limit((string) ($first['product_name'] ?? ''), 160, ''),
                'seller_code' => ($first['contribution_goods'] ?? '') ?: null,
                'trademark_id' => $trademarkId,
                'description' => (string) ($first['description'] ?? ''),
                'bullet_points' => $urls($first, 'bullet_point_', 5)->all(),
                'detail_images' => $urls($first, 'detail_image_url_', 5)->all(),
                'video_url' => ($first['product_video_url'] ?? '') ?: null,
                'detail_video_url' => ($first['detail_video_url'] ?? '') ?: null,
                'product_details' => $details,
                'variation_theme' => $theme ?: null,
                'price_cents' => $theme ? 0 : self::cents($first['base_price'] ?? null),
                'inventory_quantity' => $theme ? 0 : (int) ($first['quantity'] ?? 0),
                'handling_days' => $handling,
                'shipping_template_id' => $templateId,
                'country_of_origin' => trim(($first['country_of_origin'] ?? '').(($first['province_of_origin'] ?? '') !== '' ? ' ('.$first['province_of_origin'].')' : '')) ?: null,
                'price_references' => ($first['price_reference_url'] ?? '') !== '' ? [$first['price_reference_url']] : null,
            ];
            if (Market::taxInclusive($shop->market)) {
                $data['hsn_code'] = ($first['hsn_code'] ?? '') ?: null;
                $data['gst_rate_bps'] = ($first['gst_rate'] ?? '') !== '' ? (int) round((float) $first['gst_rate'] * 100) : null;
                $data['manufacturer_info'] = ($first['manufacturer_info'] ?? '') ?: null;
                foreach (['hsn_code' => 'HSN code', 'gst_rate_bps' => 'GST rate', 'manufacturer_info' => 'Manufacturer / packer / importer'] as $key => $label) {
                    if (empty($data[$key])) {
                        $messages[] = $label.' is required.';
                    }
                }
            }

            $messages = [...$messages, ...array_values(ProductCatalog::listingErrors($data, $category, $variants, $images, $shop->shipsItself()))];
            if (count($variants) > Sku::MAX_VARIANTS) {
                $messages[] = 'A product can have at most '.Sku::MAX_VARIANTS.' SKUs.';
                $variants = array_slice($variants, 0, Sku::MAX_VARIANTS);
            }

            $productId = null;
            if ($data['name'] !== '') {
                $productId = DB::transaction(function () use ($shop, $data, $variants, $images, $messages) {
                    $product = Product::create($data + [
                        'shop_id' => $shop->id,
                        'status' => $messages ? 'draft' : 'pending',
                        'slug' => self::slug($data['name']),
                        'sku' => Sku::nextForShop($shop),
                    ]);
                    ProductVariants::sync($product, $variants);
                    ProductImages::sync($product, $images);

                    return $product->id;
                });
            } else {
                $messages[] = 'Enter a product name.';
            }

            $status = ! $productId ? 'failed' : ($messages ? 'draft' : 'submitted');
            foreach ($group as $g) {
                $results[] = ['row' => $g['index'], 'status' => $status, 'product_id' => $productId, 'messages' => array_values(array_unique($messages))];
            }
            if ($messages) {
                $errorRows += count($group);
            }
        }

        usort($results, fn ($a, $b) => $a['row'] <=> $b['row']);

        return ['results' => $results, 'error_records' => $errorRows];
    }

    private static function cents(mixed $value): int
    {
        $number = (float) preg_replace('/[^\d.]/', '', (string) $value);

        return (int) round($number * 100);
    }

    private static function int(mixed $value): ?int
    {
        $digits = preg_replace('/[^\d]/', '', (string) $value);

        return $digits === '' ? null : (int) $digits;
    }

    private static function label(string $column): string
    {
        return Str::of($column)->replace('_', ' ')->ucfirst()->toString();
    }

    private static function slug(string $name): string
    {
        $base = Str::slug($name) ?: 'product';
        $slug = $base;
        for ($n = 2; Product::where('slug', $slug)->exists(); $n++) {
            $slug = "{$base}-{$n}";
        }

        return $slug;
    }
}
