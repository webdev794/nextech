<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Support\ProductCatalog;
use App\Support\ProductImages;
use App\Support\ProductVariants;
use App\Support\SellerLedger;
use App\Support\Sku;
use App\Support\Market;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdminProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['sometimes', 'string', 'max:100'],
            'category_id' => ['sometimes', 'integer', 'exists:categories,id'],
            'store_id' => ['sometimes', 'integer', 'exists:stores,id'],
            'status' => ['sometimes', Rule::in(['pending', 'approved', 'rejected', 'draft'])],
            'sort' => ['sometimes', Rule::in(['newest', 'oldest', 'name', 'stock_low', 'stock_high'])],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:1000'],
        ]);

        $storeId = $validated['store_id'] ?? null;
        $sort = $validated['sort'] ?? 'newest';

        $products = Product::query()
            ->inMarket(Market::fromRequest($request))
            ->with(['category:id,name,slug', 'shop:id,name', 'variants', 'storeInventory', 'images', 'trademark:id,name'])
            ->withCount(['salesBoostOffers as low_traffic_offers' => fn ($q) => $q->where('status', 'pending')])
            // When filtering by store, `effective_stock` is that store's on-hand
            // count (its stocked row, else the product's single count); otherwise
            // it's just the single count. Used by the stock sorts.
            ->select('products.*')
            ->when($storeId, fn ($query) => $query
                ->leftJoin('store_inventory as si', fn ($join) => $join
                    ->on('si.product_id', '=', 'products.id')
                    ->whereNull('si.product_variant_id')
                    ->where('si.store_id', $storeId))
                ->selectRaw('COALESCE(si.quantity, products.inventory_quantity) as effective_stock')
                ->visibleAtStore($storeId), fn ($query) => $query
                ->selectRaw('products.inventory_quantity as effective_stock'))
            ->when($validated['search'] ?? null, fn ($query, $search) => $query->where(
                fn ($inner) => $inner->where('products.name', 'like', "%{$search}%")->orWhere('products.sku', 'like', "%{$search}%")
            ))
            ->when($validated['category_id'] ?? null, fn ($query, $id) => $query->where('products.category_id', $id))
            // Sellers' unfinished drafts (Manage products -> Incomplete) stay out of the review list.
            ->when($validated['status'] ?? null, fn ($query, $status) => $query->where('products.status', $status), fn ($query) => $query->where('products.status', '!=', 'draft'))
            ->when($sort === 'newest', fn ($query) => $query->orderByDesc('products.created_at')->orderByDesc('products.id'))
            ->when($sort === 'oldest', fn ($query) => $query->orderBy('products.created_at')->orderBy('products.id'))
            ->when($sort === 'name', fn ($query) => $query->orderBy('products.name'))
            ->when($sort === 'stock_low', fn ($query) => $query->orderBy('effective_stock')->orderBy('products.name'))
            ->when($sort === 'stock_high', fn ($query) => $query->orderByDesc('effective_stock')->orderBy('products.name'))
            ->paginate($validated['per_page'] ?? 10);

        // Required compliance documents a seller product still lacks — it can't be approved until they're in.
        foreach ($products->items() as $item) {
            $item->setAttribute('missing_compliance', $item->shop_id ? ProductCatalog::missingCompliance($item) : []);
        }

        return response()->json([
            'data' => $products->items(),
            'meta' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        // NexTech's own product: the country picked in the form, else the one the admin is working in.
        $data['market'] = ($data['shop_id'] ?? null) ? null : ($data['market'] ?? Market::fromRequest($request));
        if ($data['market'] === null) {
            unset($data['market']);
        }
        $variants = $this->pullVariants($data);
        $storeStock = $this->pullStoreStock($data);
        $images = $this->pullImages($data);
        $data['slug'] ??= $this->uniqueSlug($data['name']);
        $customSku = trim((string) ($data['sku'] ?? '')) ?: null;
        unset($data['sku']);
        // Admin-created products are never seller-moderated — always approved,
        // regardless of anything the payload sends.
        $data['status'] = 'approved';

        $product = DB::transaction(function () use ($data, $customSku, $variants, $storeStock, $images): Product {
            // sku is NOT NULL+unique and depends on the row's own id, which
            // only exists after insert — create with a throwaway placeholder,
            // then immediately overwrite it, all inside this transaction so
            // the placeholder is never visible outside it.
            $product = Product::create($data + ['sku' => 'TMP-'.Str::random(20)]);
            $product->update(['sku' => $customSku ?? Sku::forAdminProduct($product->id)]);
            ProductImages::sync($product, $images, firstIsMain: false);
            $variantIndexToId = ProductVariants::sync($product, $variants, allowSkuOverride: true);
            $this->syncStoreStock($product, $storeStock, $variantIndexToId);

            return $product;
        });

        return response()->json(['data' => $product->load('category:id,name', 'shop:id,name', 'variants', 'storeInventory', 'images')], 201);
    }

    public function update(Request $request, Product $product): JsonResponse
    {
        $data = $this->validated($request, $product);
        // A seller product's country is always its shop's.
        if (($data['shop_id'] ?? $product->shop_id) !== null) {
            unset($data['market']);
        }
        $variants = $this->pullVariants($data);
        $storeStock = $this->pullStoreStock($data);
        $images = $this->pullImages($data);
        $data['status'] = 'approved';
        if (trim((string) ($data['sku'] ?? '')) === '') {
            unset($data['sku']);
        }

        DB::transaction(function () use ($product, $data, $variants, $storeStock, $images): void {
            $product->update($data);
            ProductImages::sync($product, $images, firstIsMain: false);
            $variantIndexToId = ProductVariants::sync($product, $variants, allowSkuOverride: true);
            $this->syncStoreStock($product, $storeStock, $variantIndexToId);
        });

        return response()->json(['data' => $product->fresh()->load('category:id,name', 'shop:id,name', 'variants', 'storeInventory', 'images')]);
    }

    /**
     * Approve a seller-submitted product. Only meaningful for a shop-owned
     * row — an admin-owned product (shop_id null) is always already
     * 'approved' via store()/update() forcing it, so this is a no-op there.
     */
    public function approve(Product $product): JsonResponse
    {
        abort_unless($product->shop_id !== null, 422, 'Only seller products go through review.');
        abort_if($product->status === 'draft', 422, 'The seller hasn’t submitted this product yet.');
        $missing = ProductCatalog::missingCompliance($product);
        abort_if($missing !== [], 422, 'Compliance documents missing: '.implode(', ', $missing).'. Ask the seller to upload them under Products -> Product compliance.');

        $product->forceFill(['status' => 'approved', 'rejection_reason' => null])->save();

        return response()->json(['data' => $product->fresh()->load('category:id,name', 'shop:id,name', 'variants', 'storeInventory', 'images')]);
    }

    public function reject(Request $request, Product $product): JsonResponse
    {
        abort_unless($product->shop_id !== null, 422, 'Only seller products go through review.');

        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        $product->forceFill(['status' => 'rejected', 'rejection_reason' => $data['reason']])->save();

        return response()->json(['data' => $product->fresh()->load('category:id,name', 'shop:id,name', 'variants', 'storeInventory', 'images')]);
    }

    public function destroy(Product $product): JsonResponse
    {
        try {
            $product->delete();
        } catch (QueryException) {
            return response()->json([
                'message' => 'This product belongs to existing orders. Deactivate it instead of deleting.',
            ], 409);
        }

        return response()->json(status: 204);
    }

    private function validated(Request $request, ?Product $product = null): array
    {
        $unique = Rule::unique('products')->ignore($product?->id);

        return $request->validate([
            'category_id' => [$product ? 'sometimes' : 'required', 'integer', 'exists:categories,id'],
            'shop_id' => ['sometimes', 'nullable', 'integer', 'exists:shops,id'],
            'name' => [$product ? 'sometimes' : 'required', 'string', 'max:160'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:180', 'alpha_dash', $unique],
            // Optional admin override — blank means "auto-generate" (on create)
            // or "keep the current one" (on update).
            'sku' => ['sometimes', 'nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9_-]+$/', Rule::unique('products', 'sku')->ignore($product?->id)],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'price_cents' => [$product ? 'sometimes' : 'required', 'integer', 'min:0'],
            'compare_at_price_cents' => ['sometimes', 'nullable', 'integer', 'min:0'],
            // Days after delivery the item can be returned; null = platform default, 0 = non-returnable.
            'return_days' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:'.SellerLedger::maxReturnDays()],
            'shipping_template_id' => ['sometimes', 'nullable', 'integer', 'exists:shipping_templates,id'],
            ...Market::productRules(null, false),
            'market' => ['sometimes', 'string', Rule::in(Market::codes())],
            'inventory_quantity' => ['sometimes', 'integer', 'min:0'],
            'image_url' => ['sometimes', 'nullable', 'string', 'max:500'],
            'images' => ['sometimes', 'array', 'max:'.ProductImages::MAX_IMAGES],
            'images.*' => ['string', 'max:500'],
            'video_url' => ['sometimes', 'nullable', 'string', 'max:500'],
            'is_active' => ['sometimes', 'boolean'],
            'deal_type' => ['sometimes', 'nullable', Rule::in(['lightning', 'unbeatable'])],
            'is_exclusive_offer' => ['sometimes', 'boolean'],

            // Per-store stock. A full replacement of this product's rows: one
            // entry per (store, option). `variant_index` null = the base product.
            // A product with no entries stays on single stock (inventory_quantity).
            'store_stock' => ['sometimes', 'array'],
            'store_stock.*.store_id' => ['required', 'integer', 'exists:stores,id'],
            // Position of the variant row in the `variants` array this same
            // request sends (null = the base product) — not the variant's
            // SKU, which the client can no longer know ahead of a save for a
            // brand-new variant since it's server-generated.
            'store_stock.*.variant_index' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'store_stock.*.is_stocked' => ['sometimes', 'boolean'],
            'store_stock.*.quantity' => ['sometimes', 'integer', 'min:0', 'max:1000000'],

            'variants' => ['sometimes', 'array', 'max:'.(Sku::MAX_VARIANTS * 2)], // live + _delete rows; the real cap is enforced in ProductVariants::sync
            'variants.*.id' => ['sometimes', 'nullable', 'integer'],
            'variants.*._delete' => ['sometimes', 'boolean'],
            'variants.*.label' => ['required_with:variants', 'string', 'max:80'],
            'variants.*.sku' => ['sometimes', 'nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9_-]+$/'],
            'variants.*.price_cents' => ['required_with:variants', 'integer', 'min:0'],
            'variants.*.compare_at_price_cents' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'variants.*.inventory_quantity' => ['sometimes', 'integer', 'min:0'],
            'variants.*.image_url' => ['sometimes', 'nullable', 'string', 'max:500'],
            'variants.*.sort_order' => ['sometimes', 'integer', 'min:0'],
            'variants.*.is_active' => ['sometimes', 'boolean'],
        ]);
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
     * @return array<int, array<string, mixed>>|null
     */
    private function pullStoreStock(array &$data): ?array
    {
        if (! array_key_exists('store_stock', $data)) {
            return null;
        }

        $rows = $data['store_stock'];
        unset($data['store_stock']);

        return $rows;
    }

    /**
     * Replace this product's per-store stock with the submitted grid. Each entry
     * is upserted; a row not in the payload is removed (a store or variant the
     * admin dropped). An empty array clears per-store stock — the product falls
     * back to its single `inventory_quantity`.
     *
     * @param  array<int, array<string, mixed>>|null  $rows
     * @param  array<int, int>  $variantIndexToId  from ProductVariants::sync() — maps a
     *                                              row's position in the `variants` payload
     *                                              this same request sent to its variant id.
     */
    private function syncStoreStock(Product $product, ?array $rows, array $variantIndexToId): void
    {
        if ($rows === null) {
            return;
        }

        $keep = [];

        foreach ($rows as $row) {
            $index = $row['variant_index'] ?? null;
            $variantId = $index === null ? null : ($variantIndexToId[$index] ?? null);

            if ($index !== null && $variantId === null) {
                continue; // references a variant that was deleted in this same save
            }

            $entry = $product->storeInventory()->updateOrCreate(
                ['store_id' => (int) $row['store_id'], 'product_variant_id' => $variantId],
                [
                    'quantity' => (int) ($row['quantity'] ?? 0),
                    'is_stocked' => (bool) ($row['is_stocked'] ?? true),
                ],
            );
            $keep[] = $entry->id;
        }

        $product->storeInventory()->whereNotIn('id', $keep)->delete();
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $suffix = 2;

        while (Product::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
