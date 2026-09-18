<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
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

        return response()->json([
            'data' => Category::query()
                ->where('is_active', true)
                // When a store serves this customer, hide a category with nothing
                // for sale there (an out-of-stock item still counts). With no
                // store in context, list them all as before.
                ->when($storeId !== null, fn ($query) => $query->whereHas(
                    'products',
                    fn ($inner) => $inner->where('is_active', true)->visibleAtStore($storeId),
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
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);

        $storeId = $this->servingStoreId($request);

        $products = Product::query()
            ->with([
                'category',
                'variants' => fn ($query) => $query->where('is_active', true),
                'storeInventory',
            ])
            ->where('is_active', true)
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
            ->when(isset($validated['deal_type']), fn ($query) => $query->where('deal_type', $validated['deal_type']))
            ->when($validated['exclusive'] ?? false, fn ($query) => $query->where('is_exclusive_offer', true))
            ->orderBy('name')
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
            ])
            ->where('is_active', true)
            ->where('deal_type', $validated['deal_type'])
            ->when($validated['exclusive'] ?? false, fn ($query) => $query->where('is_exclusive_offer', true))
            ->visibleAtStore($storeId)
            ->whereHas('category', fn ($query) => $query->where('is_active', true))
            ->inRandomOrder()
            ->limit($validated['limit'] ?? 3)
            ->get()
            ->map(fn (Product $product) => $this->present($product, $storeId));

        return response()->json(['data' => $products]);
    }

    public function product(Request $request, Product $product): JsonResponse
    {
        $storeId = $this->servingStoreId($request);

        $product->load([
            'category',
            'variants' => fn ($query) => $query->where('is_active', true),
            'storeInventory',
        ]);

        abort_unless(
            $product->is_active
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

        // storeInventory was only loaded to compute the above; don't ship it.
        $product->unsetRelation('storeInventory');

        return $product;
    }
}
