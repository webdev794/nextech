<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Support\ProductCatalog;
use App\Support\StoreDecorations;
use App\Support\SellerRequirements;
use App\Models\ProductVariant;
use App\Models\Shop;
use App\Support\Country;
use App\Support\Fx;
use App\Support\Market;
use App\Support\StoreLocator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CatalogController extends Controller
{
    /**
     * The store that serves the lat/lng on the request, if any. Its id scopes
     * per-store stock: a customer outside every store's radius (or one who
     * hasn't set a location) gets the full catalog and is stopped at checkout.
     */
    private function servingStoreId(Request $request): ?int
    {
        $data = $request->validate([
            'lat' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'lng' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
        ]);

        return StoreLocator::servingStore(
            isset($data['lat']) ? (float) $data['lat'] : null,
            isset($data['lng']) ? (float) $data['lng'] : null,
        )?->id;
    }

    /** Shop columns the catalog needs: shipping terms for cross-border products, name/link for the product page. */
    // seller_id: whether the shop is live (Product::hiddenFromShoppers) needs its seller.
    private const SHOP_COLUMNS = 'id,seller_id,name,slug,market,fulfillment_mode,intl_shipping';

    /**
     * A product shipped in from another country's seller: prices converted
     * into the shopper's currency (with the platform margin), plus where it
     * ships from and the seller's international shipping fee and transit.
     */
    private function presentCrossBorder(Product $product, ?string $market): void
    {
        $terms = $market ? $product->crossBorderTerms($market) : null;
        if (! $terms) {
            return;
        }
        $from = Market::currency($product->market);
        $to = Market::currency($market);
        $rate = Fx::multiplier($from, $to);
        $conv = fn ($cents) => $cents === null ? null : Fx::convert((int) $cents, $from, $to, $rate);

        $product->setAttribute('price_cents', $conv($product->price_cents));
        $product->setAttribute('compare_at_price_cents', $conv($product->compare_at_price_cents));
        foreach ($product->variants as $variant) {
            $variant->setAttribute('price_cents', $conv($variant->price_cents));
            $variant->setAttribute('compare_at_price_cents', $conv($variant->compare_at_price_cents));
        }
        $product->setAttribute('currency', $to);
        $product->setAttribute('ships_from', $product->market);
        $product->setAttribute('ships_from_name', Country::find($product->market)['name'] ?? $product->market);
        $product->setAttribute('intl_shipping', [
            'fee_cents' => $conv((int) ($terms['fee_cents'] ?? 0)),
            'transit_min_days' => (int) ($terms['transit_min_days'] ?? 0),
            'transit_max_days' => (int) ($terms['transit_max_days'] ?? 0),
        ]);
    }

    public function categories(Request $request): JsonResponse
    {
        $storeId = $this->servingStoreId($request);
        $market = Market::fromRequest($request);

        return response()->json([
            'data' => Category::query()
                ->where('is_active', true)
                // Outside the home market, only categories that market sells in.
                ->when($market !== Market::home(), fn ($query) => $query->whereHas(
                    'products',
                    fn ($inner) => $inner->availableIn($market)->where('is_active', true)->where('status', 'approved'),
                ))
                // When a store serves this customer, hide a category with nothing
                // for sale there (an out-of-stock item still counts). With no
                // store in context, list them all as before.
                ->when($storeId !== null, fn ($query) => $query->whereHas(
                    'products',
                    fn ($inner) => $inner->where('is_active', true)->where('status', 'approved')->visibleAtStore($storeId),
                ))
                // Demo products hidden: drop categories that only had demo products.
                ->when(Product::demosHidden(), fn ($query) => $query->whereHas(
                    'products',
                    fn ($inner) => $inner->where('is_active', true)->where('status', 'approved')->shownToShoppers(),
                ))
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function products(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category' => ['sometimes', 'string', 'exists:categories,slug'],
            'search' => ['sometimes', 'string', 'min:2', 'max:100'],
            'deal_type' => ['sometimes', Rule::in(['lightning', 'unbeatable'])],
            'exclusive' => ['sometimes', 'boolean'],
            'sort' => ['sometimes', Rule::in(['best_selling', 'top_rated', 'newest'])],
            'shop' => ['sometimes', 'string', 'max:180'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);

        $storeId = $this->servingStoreId($request);
        $shopId = isset($validated['shop']) ? (self::publicShop($validated['shop'])?->id ?? 0) : null;
        $market = Market::fromRequest($request);

        $products = Product::query()
            ->with([
                'category',
                'variants' => fn ($query) => $query->where('is_active', true),
                'storeInventory',
                'images',
                'shop:'.self::SHOP_COLUMNS,
            ])
            ->where('is_active', true)
            ->where('status', 'approved')
            ->shownToShoppers()
            // Shoppers see their market's products and those shipped in from
            // other countries — except on a shop's own page, which lists that
            // shop whatever market it's in.
            ->when($shopId === null, fn ($query) => $query->availableIn($market))
            ->visibleAtStore($storeId)
            ->whereHas('category', fn ($query) => $query->where('is_active', true))
            ->when(isset($validated['search']), function ($query) use ($validated) {
                $search = $validated['search'];

                $query->where(function ($searchQuery) use ($search) {
                    $searchQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%")
                        ->orWhereHas('variants', fn ($variantQuery) => $variantQuery
                            ->where('label', 'like', "%{$search}%")
                            ->orWhere('sku', 'like', "%{$search}%"));
                });
            })
            ->when(isset($validated['category']), fn ($query) => $query->whereHas(
                'category',
                fn ($categoryQuery) => $categoryQuery->where('slug', $validated['category'])
            ))
            ->when($shopId !== null, fn ($query) => $query->where('shop_id', $shopId))
            ->when(isset($validated['deal_type']), fn ($query) => $query->where('deal_type', $validated['deal_type']))
            ->when($validated['exclusive'] ?? false, fn ($query) => $query->where('is_exclusive_offer', true))
            ->when(($validated['sort'] ?? null) === 'top_rated', fn ($query) => $query->where('rating_avg', '>=', 4.5))
            // "Low traffic" products (a sales boost offer still pending) rank below the rest.
            ->orderByRaw("EXISTS (SELECT 1 FROM sales_boost_offers sbo WHERE sbo.product_id = products.id AND sbo.status = 'pending')")
            ->when(
                $validated['sort'] ?? null,
                fn ($query, $sort) => match ($sort) {
                    'best_selling' => $query->orderByDesc('units_sold'),
                    'top_rated' => $query->orderByDesc('rating_avg')->orderByDesc('rating_count'),
                    'newest' => $query->orderByDesc('created_at'),
                },
                fn ($query) => $query->orderBy('name'),
            )
            ->paginate($validated['per_page'] ?? 20)
            ->through(fn (Product $product) => $this->present($product, $storeId, $market));

        return response()->json($products);
    }

    /**
     * The signed-in buyer's favourites that are on sale in the store being
     * browsed, newest saved first, shaped like any product list.
     */
    public function favorites(Request $request): JsonResponse
    {
        $market = Market::fromRequest($request);
        $ids = \App\Models\Favorite::where('user_id', $request->user()->id)->latest()->pluck('product_id');

        $products = Product::query()
            ->with(['category', 'variants' => fn ($query) => $query->where('is_active', true), 'storeInventory', 'images', 'shop:'.self::SHOP_COLUMNS])
            ->whereIn('id', $ids)
            ->where('is_active', true)
            ->where('status', 'approved')
            ->shownToShoppers()
            ->availableIn($market)
            ->get()
            ->sortBy(fn (Product $p) => $ids->search($p->id))
            ->values()
            ->map(fn (Product $product) => $this->present($product, null, $market));

        return response()->json(['data' => $products, 'saved_elsewhere' => $ids->count() - $products->count()]);
    }

    /**
     * A small random sample of products tagged with a deal type, for the
     * homepage deals strip. Re-rolled on every request — no caching — so a
     * page refresh surfaces different products.
     */
    public function deals(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'deal_type' => ['required', Rule::in(['lightning', 'unbeatable'])],
            'exclusive' => ['sometimes', 'boolean'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:24'],
        ]);

        $storeId = $this->servingStoreId($request);
        $market = Market::fromRequest($request);

        $products = Product::query()
            ->with([
                'category',
                'variants' => fn ($query) => $query->where('is_active', true),
                'storeInventory',
                'images',
                'shop:'.self::SHOP_COLUMNS,
            ])
            ->where('is_active', true)
            ->where('status', 'approved')
            ->where('deal_type', $validated['deal_type'])
            ->shownToShoppers()
            ->availableIn($market)
            ->when($validated['exclusive'] ?? false, fn ($query) => $query->where('is_exclusive_offer', true))
            ->visibleAtStore($storeId)
            ->whereHas('category', fn ($query) => $query->where('is_active', true))
            ->inRandomOrder()
            ->limit($validated['limit'] ?? 3)
            ->get()
            ->map(fn (Product $product) => $this->present($product, $storeId, $market));

        return response()->json(['data' => $products]);
    }

    /**
     * A seller's public shop page: name, logo/banner, description and product
     * count — the storefront lists its products via GET /products?shop=<slug>.
     * Only live shops (active, seller approved) are visible.
     */
    public function shop(string $slug): JsonResponse
    {
        $shop = self::publicShop($slug);
        abort_unless($shop, 404, 'Shop not found.');

        // Only the categories this shop actually sells in, for the shop
        // page's category strip.
        $live = $shop->products()
            ->where('is_active', true)
            ->where('status', 'approved')
            ->shownToShoppers()
            ->whereHas('category', fn ($q) => $q->where('is_active', true));
        $categories = Category::query()
            ->whereIn('id', (clone $live)->select('category_id'))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'image_url'])
            ->map(fn (Category $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'slug' => $c->slug,
                'image_url' => $c->image_url,
                'count' => (clone $live)->where('category_id', $c->id)->count(),
            ]);

        return response()->json(['data' => [
            'name' => $shop->name,
            'slug' => $shop->slug,
            'market' => $shop->market,
            'logo_url' => $shop->logo_url,
            'banner_url' => $shop->banner_url,
            'description' => $shop->description,
            'category' => $shop->category?->name,
            'since' => $shop->created_at?->toDateString(),
            'products_count' => (clone $live)->count(),
            'categories' => $categories,
            // The seller's decorated store page, per platform — only once the
            // store has enough live products; otherwise the default page.
            'decoration' => (! SellerRequirements::on($shop, 'store_min_products') || (clone $live)->count() >= StoreDecorations::minProducts())
                ? collect(StoreDecorations::PLATFORMS)->mapWithKeys(fn ($platform) => [$platform => ($d = $shop->decorations()->where('platform', $platform)->where('is_live', true)->first()) ? StoreDecorations::resolve($d, $shop) : null])->all()
                : null,
        ]]);
    }

    private static function publicShop(string $slug): ?Shop
    {
        return Shop::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->whereHas('seller', fn ($q) => $q->where('status', 'approved'))
            ->first();
    }

    public function product(Request $request, Product $product): JsonResponse
    {
        $storeId = $this->servingStoreId($request);

        $product->load([
            'shop:'.self::SHOP_COLUMNS.',is_active',
            'category',
            'variants' => fn ($query) => $query->where('is_active', true),
            'storeInventory',
            'images',
            'trademark:id,name,logo_url',
        ]);

        // Taken off sale by the seller, or removed after they asked: past
        // buyers can still open it (warranty, reviews) — it just can't be bought.
        $noLongerSold = ! $product->is_active && $product->status === 'approved'
            && ($product->deactivated_by === 'seller' || $product->archived_at !== null);

        abort_unless(
            ($product->is_active || $noLongerSold)
                && $product->status === 'approved'
                && ! $product->hiddenFromShoppers()
                && $product->category?->is_active
                && ($storeId === null || ! $product->usesStoreInventory()
                    || $product->availabilityAt($storeId)['sold']
                    || $product->variants->contains(
                        fn (ProductVariant $v) => $product->availabilityAt($storeId, $v)['sold']
                    )),
            404
        );

        $data = $this->present($product, $storeId, Market::fromRequest($request), keepShop: true);
        if ($noLongerSold) {
            $data->setAttribute('no_longer_sold', true);
            $data->setAttribute('support_until', ProductCatalog::supportUntil($product)?->toDateString());
        }

        return response()->json(['data' => $data]);
    }

    /**
     * Shape a product for the storefront: price range, and — when a store serves
     * the customer — that store's stock. `inventory_quantity` on the product and
     * each variant is rewritten to the store figure so existing clients keep
     * working; `out_of_stock` flags a stocked-but-empty line, and variants the
     * store doesn't carry are dropped.
     */
    private function present(Product $product, ?int $storeId, ?string $market = null, bool $keepShop = false): Product
    {
        $this->presentCrossBorder($product, $market);

        if ($storeId !== null && $product->usesStoreInventory()) {
            $kept = [];

            foreach ($product->variants as $variant) {
                $availability = $product->availabilityAt($storeId, $variant);
                if (! $availability['sold']) {
                    continue; // store doesn't carry this option
                }
                $variant->setAttribute('inventory_quantity', $availability['quantity']);
                $variant->setAttribute('out_of_stock', $availability['quantity'] <= 0);
                $kept[] = $variant;
            }

            $product->setRelation('variants', collect($kept)->values());

            $base = $product->availabilityAt($storeId);
            $product->setAttribute('inventory_quantity', $base['sold'] ? $base['quantity'] : 0);
            $product->setAttribute('base_sold', $base['sold']);

            $everyOptionEmpty = ($base['sold'] ? $base['quantity'] <= 0 : true)
                && $product->variants->every(fn ($v) => $v->inventory_quantity <= 0);
            $product->setAttribute('out_of_stock', $everyOptionEmpty);
        } else {
            $product->setAttribute('base_sold', true);
            $product->setAttribute('out_of_stock', $product->inventory_quantity <= 0
                && $product->variants->every(fn ($v) => $v->inventory_quantity <= 0));
        }

        $prices = $product->variants->pluck('price_cents')->push($product->price_cents);
        $product->setAttribute('price_min_cents', (int) $prices->min());
        $product->setAttribute('price_max_cents', (int) $prices->max());
        if (! $product->getAttribute('ships_from')) {
            $product->setAttribute('currency', Market::currency($product->market));
        }

        // storeInventory was only loaded to compute the above; don't ship it.
        $product->unsetRelation('storeInventory');
        // The shop was loaded for its shipping terms; only the product page shows it (name/link).
        if (! $keepShop) {
            $product->unsetRelation('shop');
        } else {
            $product->shop?->makeHidden(['intl_shipping', 'fulfillment_mode', 'market', 'seller_id', 'seller']);
        }

        // Seller-only listing data never reaches shoppers.
        $product->makeHidden(['compliance', 'price_references', 'seller_code', 'rejection_reason', 'suggested_category_name']);
        if ($product->relationLoaded('category') && $product->product_details) {
            // Product details as labelled specifications for the product page.
            $fields = collect(ProductCatalog::attributesFor($product->category, $product->isDigital()))->keyBy('key');
            $product->setAttribute('specifications', collect($product->product_details)
                ->filter(fn ($v, $k) => $fields->has($k) && $v !== null && $v !== '' && $v !== [] && ProductCatalog::applies($fields[$k], $product->product_details))
                ->map(fn ($v, $k) => ['label' => $fields[$k]['label'], 'value' => implode(', ', (array) $v).(isset($fields[$k]['unit']) ? ' '.$fields[$k]['unit'] : '')])
                ->values());
        }

        return $product;
    }
}
