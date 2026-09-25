<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\Shop;
use App\Support\Market;
use App\Support\ProductCatalog;
use App\Support\ProductImages;
use App\Support\ProductVariants;
use App\Support\SellerLedger;
use App\Support\SellerShipping;
use App\Support\Sku;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Seller-managed products, listed the way Temu's Add product flow works:
 * category, description (bullet points, images, videos, trademark), product
 * details, variations, fulfillment (handling time + shipping template) and
 * safety & compliance. A listing can be saved as a draft (Manage products ->
 * Incomplete) at any point; submitting checks it (App\Support\ProductCatalog)
 * and sends it to NexTech for review as `pending`. Every method requires an
 * *approved* seller — the real gate, not just a frontend hide.
 */
class SellerProductController extends Controller
{
    private const RELATIONS = ['category:id,name,slug', 'variants', 'images', 'trademark:id,name,logo_url'];

    public function index(Request $request): JsonResponse
    {
        $shop = $this->shop($request);

        $products = $shop->products()
            ->with([...self::RELATIONS, 'salesBoostOffers' => fn ($q) => $q->where('status', 'pending')])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Product $p) => $this->present($p, $shop));

        return response()->json(['data' => $products]);
    }

    /** Categories, product details, variation types and compliance documents for the listing forms. */
    public function catalogConfig(Request $request): JsonResponse
    {
        $shop = $this->shop($request);

        return response()->json(['data' => ProductCatalog::clientConfig($shop->market) + [
            // "Use saved or previously used categories."
            'recent_category_ids' => $shop->products()->whereNotNull('category_id')->latest('updated_at')->pluck('category_id')->unique()->take(6)->values(),
            'trademarks' => $shop->trademarks()->where('status', 'approved')->orderBy('name')->get(['id', 'name', 'logo_url']),
            'ships_itself' => $shop->shipsItself(),
            'fulfillment_mode' => $shop->fulfillment_mode,
            'market' => $shop->market,
        ]]);
    }

    public function store(Request $request): JsonResponse
    {
        $shop = $this->shop($request);
        $submit = $request->boolean('submit', true);
        if ($submit) {
            // Like Temu: a seller who ships themselves needs a shipping template before listing.
            abort_if($shop->shipsItself() && ! $shop->shippingTemplates()->exists(), 422, 'Create a shipping template in Shipping settings before adding products.');
            // With NexTech pickup switched off, new listings need the seller's own shipping.
            abort_if(! $shop->shipsItself() && SellerShipping::nextechPickup() !== 'available', 422, 'NexTech pickup isn\'t offered anymore — set up your own shipping in Shipping settings before adding products.');
        }
        $data = $this->validated($request, $shop, null, $submit);
        $variants = $this->pullVariants($data);
        $images = $this->pullImages($data);
        if ($submit) {
            $this->assertListable($data, $variants ?? [], $images ?? [], $shop);
        }

        // Forced regardless of payload: a seller can only create products for
        // their own shop, and every new listing starts unreviewed (or as a draft).
        $data['shop_id'] = $shop->id;
        $data['status'] = $submit ? 'pending' : 'draft';
        $data['rejection_reason'] = null;
        $data['price_cents'] ??= 0;
        $data['slug'] ??= $this->uniqueSlug($data['name']);

        $product = DB::transaction(function () use ($shop, $data, $variants, $images): Product {
            $data['sku'] = Sku::nextForShop($shop);
            $product = Product::create($data);
            ProductVariants::sync($product, $variants);
            ProductImages::sync($product, $images);

            return $product;
        });

        return response()->json(['data' => $this->present($product->load(self::RELATIONS), $shop)], 201);
    }

    public function update(Request $request, Product $product): JsonResponse
    {
        $shop = $this->shop($request);
        abort_unless($product->shop_id === $shop->id, 403);
        // Only a draft can be saved without submitting; any change to a
        // submitted or live listing goes back through review.
        $submit = $request->boolean('submit', true) || $product->status !== 'draft';

        $data = $this->validated($request, $shop, $product, $submit);
        $variants = $this->pullVariants($data);
        $images = $this->pullImages($data);
        if ($submit) {
            $merged = $data + $product->only(['name', 'category_id', 'description', 'price_cents', 'country_of_origin', 'handling_days', 'product_details', 'variation_theme', 'size_chart']);
            $liveVariants = $variants !== null
                ? array_values(array_filter($variants, fn ($v) => empty($v['_delete'])))
                : $product->variants->map(fn ($v) => $v->only(['options', 'price_cents']))->all();
            $this->assertListable($merged, $liveVariants, $images ?? $product->images->pluck('url')->all(), $shop);
        }

        // Deliberately simple rule: no partial-change detection — any submit
        // goes back through review; saving an unsubmitted listing keeps it a draft.
        $data['status'] = $submit ? 'pending' : ($product->status === 'draft' ? 'draft' : $product->status);
        if ($submit) {
            $data['rejection_reason'] = null;
        }

        DB::transaction(function () use ($product, $data, $variants, $images): void {
            $product->update($data);
            ProductVariants::sync($product, $variants);
            ProductImages::sync($product, $images);
        });

        return response()->json(['data' => $this->present($product->fresh()->load(self::RELATIONS), $shop)]);
    }

    /**
     * Products -> Product compliance: add documents and the origin without
     * sending the listing back through review.
     */
    public function updateCompliance(Request $request, Product $product): JsonResponse
    {
        $shop = $this->shop($request);
        abort_unless($product->shop_id === $shop->id, 403);
        $data = $request->validate([
            'country_of_origin' => ['sometimes', 'nullable', 'string', 'max:60'],
            'documents' => ['present', 'array', 'max:20'],
            'documents.*.type' => ['required', 'string', 'max:40'],
            'documents.*.path' => ['required', 'string', 'max:255', 'starts_with:kyc/'.$request->user()->id.'/'],
            'documents.*.name' => ['nullable', 'string', 'max:255'],
        ]);

        $product->update([
            'compliance' => ['documents' => array_values($data['documents'])],
        ] + (array_key_exists('country_of_origin', $data) ? ['country_of_origin' => $data['country_of_origin']] : []));

        return response()->json(['data' => $this->present($product->fresh()->load(self::RELATIONS), $shop)]);
    }

    /**
     * The quick image upload: one draft product per uploaded image, named from
     * the file, to be completed later (Manage products -> Incomplete).
     */
    public function draftsFromImages(Request $request): JsonResponse
    {
        $shop = $this->shop($request);
        $data = $request->validate([
            'images' => ['required', 'array', 'min:1', 'max:50'],
            'images.*.url' => ['required', 'string', 'max:500'],
            'images.*.name' => ['nullable', 'string', 'max:255'],
        ]);

        $created = DB::transaction(fn () => collect($data['images'])->map(function ($image) use ($shop) {
            $name = Str::limit(trim(Str::headline(pathinfo((string) ($image['name'] ?? ''), PATHINFO_FILENAME))) ?: 'Untitled product', 150, '');
            $product = Product::create([
                'shop_id' => $shop->id, 'name' => $name, 'slug' => $this->uniqueSlug($name),
                'sku' => Sku::nextForShop($shop), 'price_cents' => 0, 'status' => 'draft',
            ]);
            ProductImages::sync($product, [$image['url']]);

            return $product->id;
        }));

        return response()->json(['data' => ['created' => $created->count(), 'ids' => $created->values()]], 201);
    }

    public function destroy(Request $request, Product $product): JsonResponse
    {
        $shop = $this->shop($request);
        abort_unless($product->shop_id === $shop->id, 403);

        try {
            $product->delete();
        } catch (QueryException) {
            return response()->json([
                'message' => 'This product belongs to existing orders. Deactivate it instead of deleting.',
            ], 409);
        }

        return response()->json(status: 204);
    }

    /**
     * A product row for the seller: what's still missing for a draft, which
     * compliance documents it lacks, and whether it's "Low traffic".
     *
     * @return array<string, mixed>
     */
    private function present(Product $product, Shop $shop): array
    {
        $row = $product->toArray();
        $row['listing_errors'] = $product->status === 'draft'
            ? ProductCatalog::listingErrors($product->toArray(), $product->category, $product->variants->map(fn ($v) => $v->only(['options', 'price_cents']))->all(), $product->images->pluck('url')->all(), $shop->shipsItself())
            : [];
        $row['missing_compliance'] = ProductCatalog::missingCompliance($product);
        $row['low_traffic'] = $product->relationLoaded('salesBoostOffers') && $product->salesBoostOffers->isNotEmpty();
        unset($row['sales_boost_offers']);

        return $row;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array<string, mixed>>  $variants
     * @param  list<string>  $images
     */
    private function assertListable(array $data, array $variants, array $images, Shop $shop): void
    {
        $live = array_values(array_filter($variants, fn ($v) => empty($v['_delete'])));
        $errors = ProductCatalog::listingErrors($data, Category::find($data['category_id'] ?? null), $live, array_values(array_filter($images)), $shop->shipsItself());
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * The caller's own shop — only if their seller application is approved.
     * A pending/rejected/suspended seller gets a 403 even hitting the API
     * directly, not just a hidden frontend panel.
     */
    private function shop(Request $request): Shop
    {
        $seller = $request->user()->seller;
        abort_unless($seller?->status === 'approved', 403, 'Approved seller access required.');

        $shop = $seller->shop;
        abort_unless($shop, 404, 'No shop found for this seller.');

        return $shop;
    }

    private function validated(Request $request, Shop $shop, ?Product $product, bool $submit): array
    {
        $unique = Rule::unique('products')->ignore($product?->id);
        $market = $shop->market;

        $data = $request->validate([
            'category_id' => ['sometimes', 'nullable', 'integer', 'exists:categories,id'],
            'name' => [$product ? 'sometimes' : 'required', 'string', 'max:160'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:180', 'alpha_dash', $unique],
            'seller_code' => ['sometimes', 'nullable', 'string', 'max:60'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'bullet_points' => ['sometimes', 'nullable', 'array', 'max:6'],
            'bullet_points.*' => ['nullable', 'string', 'max:500'],
            'price_cents' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'compare_at_price_cents' => ['sometimes', 'nullable', 'integer', 'min:0'],
            // Links to the same product elsewhere, backing up the price.
            'price_references' => ['sometimes', 'nullable', 'array', 'max:5'],
            'price_references.*' => ['nullable', 'url', 'max:500'],
            // Days after delivery the item can be returned; null = platform default, 0 = non-returnable.
            'return_days' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:'.SellerLedger::maxReturnDays()],
            // Which of the shop's shipping templates this ships under (null = the default).
            'shipping_template_id' => ['sometimes', 'nullable', 'integer', Rule::exists('shipping_templates', 'id')->where('shop_id', $shop->id)],
            'handling_days' => ['sometimes', 'nullable', 'integer', Rule::in((array) config('product_catalog.handling_days'))],
            'inventory_quantity' => ['sometimes', 'integer', 'min:0'],
            'image_url' => ['sometimes', 'nullable', 'string', 'max:500'],
            'video_url' => ['sometimes', 'nullable', 'string', 'max:500'],
            'detail_video_url' => ['sometimes', 'nullable', 'string', 'max:500'],
            'detail_images' => ['sometimes', 'nullable', 'array', 'max:20'],
            'detail_images.*' => ['string', 'max:500'],
            'trademark_id' => ['sometimes', 'nullable', 'integer', Rule::exists('trademarks', 'id')->where('shop_id', $shop->id)->where('status', 'approved')],
            'product_details' => ['sometimes', 'nullable', 'array'],
            'variation_theme' => ['sometimes', 'nullable', 'array', 'max:'.config('product_catalog.max_variation_levels', 2)],
            'variation_theme.*' => ['string', Rule::in((array) config('product_catalog.variation_types'))],
            'size_chart' => ['sometimes', 'nullable', 'array'],
            'size_chart.size_family' => ['sometimes', 'nullable', 'string', 'max:40'],
            'size_chart.sub_size_family' => ['sometimes', 'nullable', 'string', 'max:40'],
            'size_chart.rows' => ['sometimes', 'array', 'max:30'],
            'size_chart.rows.*.size' => ['required', 'string', 'max:20'],
            'size_chart.rows.*.local_size' => ['nullable', 'string', 'max:20'],
            'size_chart.rows.*.measurements' => ['sometimes', 'array'],
            'compliance' => ['sometimes', 'nullable', 'array'],
            'compliance.documents' => ['sometimes', 'array', 'max:20'],
            'compliance.documents.*.type' => ['required', 'string', 'max:40'],
            'compliance.documents.*.path' => ['required', 'string', 'max:255', 'starts_with:kyc/'.$request->user()->id.'/'],
            'compliance.documents.*.name' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
            // Nothing existing fits — a free-text hint for admin to act on
            // manually; never becomes a real category on its own.
            'suggested_category_name' => ['sometimes', 'nullable', 'string', 'max:160'],

            // Full-replace gallery — see ProductImages::sync().
            'images' => ['sometimes', 'array', 'max:'.ProductImages::MAX_IMAGES],
            'images.*' => ['string', 'max:500'],

            'variants' => ['sometimes', 'array', 'max:'.(Sku::MAX_VARIANTS * 2)], // live + _delete rows; the real cap is enforced in ProductVariants::sync
            'variants.*.id' => ['sometimes', 'nullable', 'integer'],
            'variants.*._delete' => ['sometimes', 'boolean'],
            'variants.*.label' => ['required_with:variants', 'string', 'max:80'],
            'variants.*.options' => ['sometimes', 'nullable', 'array'],
            'variants.*.options.*' => ['nullable', 'string', 'max:60'],
            'variants.*.seller_code' => ['sometimes', 'nullable', 'string', 'max:60'],
            'variants.*.price_cents' => ['required_with:variants', 'integer', 'min:0'],
            'variants.*.compare_at_price_cents' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'variants.*.inventory_quantity' => ['sometimes', 'integer', 'min:0'],
            'variants.*.image_url' => ['sometimes', 'nullable', 'string', 'max:500'],
            'variants.*.weight_grams' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:1000000'],
            'variants.*.length_mm' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:100000'],
            'variants.*.width_mm' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:100000'],
            'variants.*.height_mm' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:100000'],
            'variants.*.sort_order' => ['sometimes', 'integer', 'min:0'],
            'variants.*.is_active' => ['sometimes', 'boolean'],
        ] + Market::productRules($market, $product === null && $submit));

        foreach (['bullet_points', 'price_references', 'detail_images'] as $list) {
            if (array_key_exists($list, $data) && is_array($data[$list])) {
                $data[$list] = array_values(array_filter(array_map(fn ($v) => is_string($v) ? trim($v) : $v, $data[$list])));
            }
        }

        // India: the regular price is the MRP (inclusive of all taxes) and the
        // selling price can never exceed it.
        if (Market::taxInclusive($market)) {
            $price = $data['price_cents'] ?? $product?->price_cents;
            $mrp = array_key_exists('compare_at_price_cents', $data) ? $data['compare_at_price_cents'] : $product?->compare_at_price_cents;
            abort_if($mrp !== null && $price !== null && $price > 0 && $price > $mrp, 422, 'The selling price can\'t be above the MRP.');
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, array<string, mixed>>|null
     */
    private function pullVariants(array &$data): ?array
    {
        if (! array_key_exists('variants', $data)) {
            return null;
        }

        $variants = $data['variants'];
        unset($data['variants']);

        return $variants;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, string>|null
     */
    private function pullImages(array &$data): ?array
    {
        if (! array_key_exists('images', $data)) {
            return null;
        }

        $images = $data['images'];
        unset($data['images']);

        return $images;
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'product';
        $slug = $base;
        $suffix = 2;

        while (Product::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
