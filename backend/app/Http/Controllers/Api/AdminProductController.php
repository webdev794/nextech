<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Setting;
use App\Notifications\SellerProductFollowup;
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
            'status' => ['sometimes', Rule::in(['pending', 'approved', 'rejected', 'draft', 'unapproved', 'followups', 'deletion'])],
            'sort' => ['sometimes', Rule::in(['newest', 'oldest', 'name', 'stock_low', 'stock_high'])],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:1000'],
            'demo' => ['sometimes', Rule::in(['only', 'none'])],
        ]);

        $storeId = $validated['store_id'] ?? null;
        $sort = $validated['sort'] ?? 'newest';

        $products = Product::query()
            ->when(Market::adminFilter($request), fn ($q, $m) => $q->inMarket($m))
            ->whereNull('products.archived_at')
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
            ->when($validated['search'] ?? null, fn ($query, $search) => self::search($query, $search))
            ->when($validated['category_id'] ?? null, fn ($query, $id) => $query->where('products.category_id', $id))
            ->when($validated['demo'] ?? null, fn ($query, $demo) => $query->where('products.is_demo', $demo === 'only'))
            ->tap(fn ($query) => self::byStatus($query, $validated['status'] ?? null))
            ->when($sort === 'newest', fn ($query) => $query->orderByDesc('products.created_at')->orderByDesc('products.id'))
            ->when($sort === 'oldest', fn ($query) => $query->orderBy('products.created_at')->orderBy('products.id'))
            ->when($sort === 'name', fn ($query) => $query->orderBy('products.name'))
            ->when($sort === 'stock_low', fn ($query) => $query->orderBy('effective_stock')->orderBy('products.name'))
            ->when($sort === 'stock_high', fn ($query) => $query->orderByDesc('effective_stock')->orderBy('products.name'))
            ->paginate($validated['per_page'] ?? 10);

        // Required compliance documents a seller product still lacks — it can't be approved until they're in.
        foreach ($products->items() as $item) {
            $item->setAttribute('missing_compliance', $item->shop_id ? ProductCatalog::missingCompliance($item) : []);
            // Removal requested: until when past buyers may still need it (returns / warranty).
            if ($item->deletion_requested_at) {
                $item->setAttribute('support_until', ProductCatalog::supportUntil($item)?->toDateString());
            }
            // Waiting on admin, or live with details still to add: what's missing.
            if ($item->shop_id && $item->status !== 'rejected') {
                $item->setAttribute('followups', ProductCatalog::followups($item));
            }
        }

        return response()->json([
            'data' => $products->items(),
            'meta' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
                // Demo products: how many (in this market view) and whether they're hidden from the store.
                'demo_count' => Product::query()->when(Market::adminFilter($request), fn ($q, $m) => $q->inMarket($m))->where('is_demo', true)->count(),
                'demos_hidden' => Product::demosHidden(),
                // For the quick filters: seller products not approved yet, and live ones still missing details.
                'status_counts' => self::statusCounts($request),
            ],
        ]);
    }

    /**
     * The list's status filter. Default (none) hides sellers' unfinished
     * drafts; 'unapproved' = everything not live yet (waiting for review,
     * drafts, rejected); 'followups' = approved anyway, details still missing.
     */
    private static function byStatus($query, ?string $status): void
    {
        match ($status) {
            null => $query->where('products.status', '!=', 'draft'),
            'unapproved' => $query->whereIn('products.status', ['pending', 'draft', 'rejected']),
            'followups' => $query->where('products.status', 'approved')->whereNotNull('products.followup_requested_at'),
            'deletion' => $query->whereNotNull('products.deletion_requested_at'),
            default => $query->where('products.status', $status),
        };
    }

    /** Name, SKU, the seller's own product code, or the seller's shop name. */
    private static function search($query, string $search): void
    {
        $query->where(fn ($inner) => $inner
            ->where('products.name', 'like', "%{$search}%")
            ->orWhere('products.sku', 'like', "%{$search}%")
            ->orWhere('products.seller_code', 'like', "%{$search}%")
            ->orWhereHas('shop', fn ($shop) => $shop->where('name', 'like', "%{$search}%")));
    }

    /** @return array<string, int> */
    private static function statusCounts(Request $request): array
    {
        $base = fn () => Product::query()->whereNull('archived_at')->when(Market::adminFilter($request), fn ($q, $m) => $q->inMarket($m));
        $byStatus = $base()->selectRaw('status, COUNT(*) as n')->groupBy('status')->pluck('n', 'status');

        return [
            'pending' => (int) ($byStatus['pending'] ?? 0),
            'draft' => (int) ($byStatus['draft'] ?? 0),
            'rejected' => (int) ($byStatus['rejected'] ?? 0),
            'unapproved' => (int) (($byStatus['pending'] ?? 0) + ($byStatus['draft'] ?? 0) + ($byStatus['rejected'] ?? 0)),
            'followups' => $base()->where('status', 'approved')->whereNotNull('followup_requested_at')->count(),
            'deletion' => $base()->whereNotNull('deletion_requested_at')->count(),
        ];
    }

    /** Mark one product as a demo product, or not. */
    public function setDemo(Request $request, Product $product): JsonResponse
    {
        $data = $request->validate(['is_demo' => ['required', 'boolean']]);
        $product->forceFill(['is_demo' => $data['is_demo']])->save();

        return response()->json(['data' => ['id' => $product->id, 'is_demo' => $product->is_demo]]);
    }

    /** Mark every product matching the list's filters (search, category, status, market) as demo, or not. */
    public function bulkDemo(Request $request): JsonResponse
    {
        $data = $request->validate([
            'is_demo' => ['required', 'boolean'],
            'search' => ['sometimes', 'nullable', 'string', 'max:100'],
            'category_id' => ['sometimes', 'nullable', 'integer', 'exists:categories,id'],
            'status' => ['sometimes', 'nullable', Rule::in(['pending', 'approved', 'rejected', 'draft', 'unapproved', 'followups'])],
            'nextech_only' => ['sometimes', 'boolean'],
        ]);
        $count = Product::query()
            ->when(Market::adminFilter($request), fn ($q, $m) => $q->inMarket($m))
            ->when($data['search'] ?? null, fn ($q, $search) => self::search($q, $search))
            ->when($data['category_id'] ?? null, fn ($q, $id) => $q->where('category_id', $id))
            ->tap(fn ($q) => self::byStatus($q, $data['status'] ?? null))
            ->when($data['nextech_only'] ?? false, fn ($q) => $q->whereNull('shop_id'))
            ->update(['is_demo' => $data['is_demo']]);

        return response()->json(['data' => ['updated' => $count]]);
    }

    /** Show or hide all demo products on the store. */
    public function demoVisibility(Request $request): JsonResponse
    {
        $data = $request->validate(['hidden' => ['required', 'boolean']]);
        Setting::put('hide_demo_products', $data['hidden']);

        return response()->json(['data' => ['demos_hidden' => Product::demosHidden()]]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        abort_if(empty($data['image_url']) && empty(array_filter((array) ($data['images'] ?? []))), 422, 'Add a product image — a product can’t go live without one.');
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
        // A product always keeps at least one image.
        if (array_key_exists('image_url', $data) && array_key_exists('images', $data)) {
            abort_if(empty($data['image_url']) && empty(array_filter((array) $data['images'])), 422, 'Add a product image — a product can’t go live without one.');
        }
        // A seller product's country is always its shop's.
        if (($data['shop_id'] ?? $product->shop_id) !== null) {
            unset($data['market']);
        }
        $variants = $this->pullVariants($data);
        $storeStock = $this->pullStoreStock($data);
        $images = $this->pullImages($data);
        $data['status'] = 'approved';
        // Hidden by NexTech: the seller can't relist it themselves.
        if (array_key_exists('is_active', $data) && (bool) $data['is_active'] !== (bool) $product->is_active) {
            $data['deactivated_by'] = $data['is_active'] ? null : 'admin';
        }
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
    public function approve(Request $request, Product $product): JsonResponse
    {
        abort_unless($product->shop_id !== null, 422, 'Only seller products go through review.');
        $followups = ProductCatalog::followups($product);
        abort_if($followups['blocking'] !== [], 422, 'Can’t go live yet: '.implode(' ', $followups['blocking']));
        // Details still missing (e.g. HSN / GST rate, compliance documents): admin
        // may approve anyway so the shop can start selling, and the seller is
        // asked to add them soon.
        if (($followups['later'] !== [] || $product->status === 'draft') && ! $request->boolean('override')) {
            return response()->json([
                'message' => 'Still missing: '.implode(' ', $followups['later'] ?: ['the seller hasn’t submitted it yet.']),
                'missing' => $followups['later'],
                'can_override' => true,
            ], 422);
        }

        $product->forceFill([
            'status' => 'approved',
            'rejection_reason' => null,
            'followup_items' => $followups['later'] ?: null,
            'followup_requested_at' => $followups['later'] ? now() : null,
        ])->save();
        if ($followups['later']) {
            try {
                $product->shop?->seller?->user?->notify(new SellerProductFollowup($product, $followups['later']));
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return response()->json(['data' => $product->fresh()->load('category:id,name', 'shop:id,name', 'variants', 'storeInventory', 'images')]);
    }

    public function reject(Request $request, Product $product): JsonResponse
    {
        abort_unless($product->shop_id !== null, 422, 'Only seller products go through review.');

        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        $product->forceFill(['status' => 'rejected', 'rejection_reason' => $data['reason']])->save();

        return response()->json(['data' => $product->fresh()->load('category:id,name', 'shop:id,name', 'variants', 'storeInventory', 'images')]);
    }

    /**
     * A seller's deletion request: remove (deleted, or archived when it's on
     * past orders — gone from the catalog and the seller's list) or decline
     * (it stays hidden; the seller can message NexTech).
     */
    public function decideDeletion(Request $request, Product $product): JsonResponse
    {
        $decision = $request->validate(['decision' => ['required', Rule::in(['remove', 'decline'])]])['decision'];
        abort_if($product->deletion_requested_at === null, 422, 'No deletion request on this product.');
        if ($decision === 'decline') {
            $product->forceFill(['deletion_requested_at' => null, 'deletion_reason' => null])->save();

            return response()->json(['data' => ['removed' => false]]);
        }
        try {
            $product->delete();
        } catch (QueryException) {
            $product->forceFill(['archived_at' => now(), 'is_active' => false, 'deactivated_by' => 'admin', 'deletion_requested_at' => null])->save();
        }

        return response()->json(['data' => ['removed' => true]]);
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
