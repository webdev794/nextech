<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductVariant;
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
            'sort' => ['sometimes', Rule::in(['newest', 'oldest', 'name', 'stock_low', 'stock_high'])],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:1000'],
        ]);

        $storeId = $validated['store_id'] ?? null;
        $sort = $validated['sort'] ?? 'newest';

        $products = Product::query()
            ->with(['category:id,name', 'variants', 'storeInventory'])
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
            ->when($sort === 'newest', fn ($query) => $query->orderByDesc('products.created_at')->orderByDesc('products.id'))
            ->when($sort === 'oldest', fn ($query) => $query->orderBy('products.created_at')->orderBy('products.id'))
            ->when($sort === 'name', fn ($query) => $query->orderBy('products.name'))
            ->when($sort === 'stock_low', fn ($query) => $query->orderBy('effective_stock')->orderBy('products.name'))
            ->when($sort === 'stock_high', fn ($query) => $query->orderByDesc('effective_stock')->orderBy('products.name'))
            ->paginate($validated['per_page'] ?? 10);

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
        $variants = $this->pullVariants($data);
        $storeStock = $this->pullStoreStock($data);
        $data['slug'] ??= $this->uniqueSlug($data['name']);

        $product = DB::transaction(function () use ($data, $variants, $storeStock): Product {
            $product = Product::create($data);
            $this->syncVariants($product, $variants);
            $this->syncStoreStock($product, $storeStock);

            return $product;
        });

        return response()->json(['data' => $product->load('category:id,name', 'variants', 'storeInventory')], 201);
    }

    public function update(Request $request, Product $product): JsonResponse
    {
        $data = $this->validated($request, $product);
        $variants = $this->pullVariants($data);
        $storeStock = $this->pullStoreStock($data);

        DB::transaction(function () use ($product, $data, $variants, $storeStock): void {
            $product->update($data);
            $this->syncVariants($product, $variants);
            $this->syncStoreStock($product, $storeStock);
        });

        return response()->json(['data' => $product->fresh()->load('category:id,name', 'variants', 'storeInventory')]);
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
            'name' => [$product ? 'sometimes' : 'required', 'string', 'max:160'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:180', 'alpha_dash', $unique],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'sku' => [$product ? 'sometimes' : 'required', 'string', 'max:60', $unique],
            'price_cents' => [$product ? 'sometimes' : 'required', 'integer', 'min:0'],
            'compare_at_price_cents' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'inventory_quantity' => ['sometimes', 'integer', 'min:0'],
            'image_url' => ['sometimes', 'nullable', 'url', 'max:500'],
            'is_active' => ['sometimes', 'boolean'],
            'deal_type' => ['sometimes', 'nullable', Rule::in(['lightning', 'unbeatable'])],
            'is_exclusive_offer' => ['sometimes', 'boolean'],

            // Per-store stock. A full replacement of this product's rows: one
            // entry per (store, option). `variant_sku` null = the base product.
            // A product with no entries stays on single stock (inventory_quantity).
            'store_stock' => ['sometimes', 'array'],
            'store_stock.*.store_id' => ['required', 'integer', 'exists:stores,id'],
            'store_stock.*.variant_sku' => ['sometimes', 'nullable', 'string', 'max:60'],
            'store_stock.*.is_stocked' => ['sometimes', 'boolean'],
            'store_stock.*.quantity' => ['sometimes', 'integer', 'min:0', 'max:1000000'],

            'variants' => ['sometimes', 'array'],
            'variants.*.id' => ['sometimes', 'nullable', 'integer'],
            'variants.*._delete' => ['sometimes', 'boolean'],
            'variants.*.label' => ['required_with:variants', 'string', 'max:80'],
            'variants.*.sku' => ['required_with:variants', 'string', 'max:60'],
            'variants.*.price_cents' => ['required_with:variants', 'integer', 'min:0'],
            'variants.*.compare_at_price_cents' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'variants.*.inventory_quantity' => ['sometimes', 'integer', 'min:0'],
            'variants.*.image_url' => ['sometimes', 'nullable', 'url', 'max:500'],
            'variants.*.sort_order' => ['sometimes', 'integer', 'min:0'],
            'variants.*.is_active' => ['sometimes', 'boolean'],
        ]);
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
     */
    private function syncStoreStock(Product $product, ?array $rows): void
    {
        if ($rows === null) {
            return;
        }

        // Resolve variant SKUs against the just-synced variants.
        $variantIdBySku = $product->variants()->pluck('id', 'sku');
        $keep = [];

        foreach ($rows as $row) {
            $sku = $row['variant_sku'] ?? null;
            $variantId = ($sku === null || $sku === '') ? null : $variantIdBySku->get($sku);

            if ($sku && $variantId === null) {
                continue; // references a variant that no longer exists
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

    /**
     * @param  array<int, array<string, mixed>>|null  $rows
     */
    private function syncVariants(Product $product, ?array $rows): void
    {
        if ($rows === null) {
            return;
        }

        foreach ($rows as $index => $row) {
            $existing = ! empty($row['id'])
                ? $product->variants()->whereKey($row['id'])->first()
                : null;

            if (! empty($row['_delete'])) {
                if ($existing) {
                    try {
                        $existing->delete();
                    } catch (QueryException) {
                        abort(422, "\"{$existing->label}\" is on an existing order — deactivate it instead of deleting.");
                    }
                }

                continue;
            }

            $skuOwner = ProductVariant::where('sku', $row['sku'])->first();
            if ($skuOwner && $skuOwner->id !== ($existing->id ?? null)) {
                abort(422, "The variant SKU \"{$row['sku']}\" is already in use.");
            }

            $compareAt = $row['compare_at_price_cents'] ?? null;

            $attributes = [
                'label' => $row['label'],
                'sku' => $row['sku'],
                'price_cents' => (int) $row['price_cents'],
                'compare_at_price_cents' => ($compareAt === null || $compareAt === '') ? null : (int) $compareAt,
                'inventory_quantity' => (int) ($row['inventory_quantity'] ?? 0),
                'image_url' => $row['image_url'] ?? null,
                'sort_order' => (int) ($row['sort_order'] ?? $index),
                'is_active' => (bool) ($row['is_active'] ?? true),
            ];

            if ($existing) {
                $existing->update($attributes);
            } else {
                $product->variants()->create($attributes);
            }
        }
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
