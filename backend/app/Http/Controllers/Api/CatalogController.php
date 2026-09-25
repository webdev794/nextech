<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Support\ProductCatalog;
use App\Models\ProductVariant;
use App\Models\Shop;
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
                    fn ($inner) => $inner->inMarket($market)->where('is_active', true)->where('status', 'approved'),
                ))
                // When a store serves this customer, hide a category with nothing
                // for sale there (an out-of-stock item still counts). With no
                // store in context, list them all as before.
                ->when($storeId !== null, fn ($query) => $query->whereHas(
                    'products',
                    fn ($inner) => $inner->where('is_active', true)->where('status', 'approved')->visibleAtStore($storeId),
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

        $products = Product::query()
            ->with([
                'category',
                'variants' => fn ($query) => $query->where('is_active', true),
                'storeInventory',
                'images',
            ])
            ->where('is_active', true)
            ->where('status', 'approved')
            // Shoppers see only their market's products — except on a shop's
            // own page, which lists that shop whatever market it's in.
            ->when($shopId === null, fn ($query) => $query->inMarket(Market::fromRequest($request)))
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
            ->through(fn (Product $product) => $this->present($product, $storeId));

        return response()->json($products);
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

        $products = Product::query()
            ->with([
                'category',
                'variants' => fn ($query) => $query->where('is_active', true),
                'storeInventory',
                'images',
            ])
            ->where('is_active', true)
            ->where('status', 'approved')
            ->where('deal_type', $validated['deal_type'])
            ->inMarket(Market::fromRequest($request))
            ->when($validated['exclusive'] ?? false, fn ($query) => $query->where('is_exclusive_offer', true))
            ->visibleAtStore($storeId)
            ->whereHas('category', fn ($query) => $query->where('is_active', true))
            ->inRandomOrder()
            ->limit($validated['limit'] ?? 3)
            ->get()
            ->map(fn (Product $product) => $this->present($product, $storeId));

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
            'shop:id,name,slug,is_active',
            'category',
            'variants' => fn ($query) => $query->where('is_active', true),
            'storeInventory',
            'images',
            'trademark:id,name,logo_url',
        ]);

        abort_unless(
            $product->is_active
                && $product->status === 'approved'
                && $product->category?->is_active
                && ($storeId === null || ! $product->usesStoreInventory()
                    || $product->availabilityAt($storeId)['sold']
                    || $product->variants->contains(
                        fn (ProductVariant $v) => $product->availabilityAt($storeId, $v)['sold']
                    )),
            404
        );

        return response()->json([
            'data' => $this->present($product, $storeId),
        ]);
    }

    /**
     * Shape a product for the storefront: price range, and — when a store serves
     * the customer — that store's stock. `inventory_quantity` on the product and
     * each variant is rewritten to the store figure so existing clients keep
     * working; `out_of_stock` flags a stocked-but-empty line, and variants the
     * store doesn't carry are dropped.
     */
    private function present(Product $product, ?int $storeId): Product
    {
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
        $product->setAttribute('currency', Market::currency($product->market));

        // storeInventory was only loaded to compute the above; don't ship it.
        $product->unsetRelation('storeInventory');

        // Seller-only listing data never reaches shoppers.
        $product->makeHidden(['compliance', 'price_references', 'seller_code', 'rejection_reason', 'suggested_category_name']);
        if ($product->relationLoaded('category') && $product->product_details) {
            // Product details as labelled specifications for the product page.
            $fields = collect(ProductCatalog::attributesFor($product->category))->keyBy('key');
            $product->setAttribute('specifications', collect($product->product_details)
                ->filter(fn ($v, $k) => $fields->has($k) && $v !== null && $v !== '' && $v !== [] && ProductCatalog::applies($fields[$k], $product->product_details))
                ->map(fn ($v, $k) => ['label' => $fields[$k]['label'], 'value' => implode(', ', (array) $v).(isset($fields[$k]['unit']) ? ' '.$fields[$k]['unit'] : '')])
                ->values());
        }

        return $product;
    }
}
