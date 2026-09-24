<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Shop;
use App\Support\ProductImages;
use App\Support\ProductVariants;
use App\Support\SellerLedger;
use App\Support\Sku;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Seller-managed products: a seller's own storefront listings, submitted as
 * `pending` for admin review before they're publicly visible (mirroring the
 * seller-approval workflow itself). Every method requires an *approved*
 * seller — this is the real, authoritative gate, not just a frontend hide.
 */
class SellerProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $shop = $this->shop($request);

        $products = $shop->products()
            ->with(['category:id,name', 'variants', 'images'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        return response()->json(['data' => $products]);
    }

    public function store(Request $request): JsonResponse
    {
        $shop = $this->shop($request);
        $data = $this->validated($request);
        $variants = $this->pullVariants($data);
        $images = $this->pullImages($data);

        // Forced regardless of payload: a seller can only create products for
        // their own shop, and every new listing starts unreviewed.
        $data['shop_id'] = $shop->id;
        $data['status'] = 'pending';
        $data['rejection_reason'] = null;
        $data['slug'] ??= $this->uniqueSlug($data['name']);

        $product = DB::transaction(function () use ($shop, $data, $variants, $images): Product {
            $data['sku'] = Sku::nextForShop($shop);
            $product = Product::create($data);
            ProductVariants::sync($product, $variants);
            ProductImages::sync($product, $images);

            return $product;
        });

        return response()->json(['data' => $product->load('category:id,name', 'variants', 'images')], 201);
    }

    public function update(Request $request, Product $product): JsonResponse
    {
        $shop = $this->shop($request);
        abort_unless($product->shop_id === $shop->id, 403);

        $data = $this->validated($request, $product);
        $variants = $this->pullVariants($data);
        $images = $this->pullImages($data);

        // Deliberately simple rule: no partial-change detection — any save at
        // all goes back through review, matching "submitted for admin review".
        $data['status'] = 'pending';
        $data['rejection_reason'] = null;

        DB::transaction(function () use ($product, $data, $variants, $images): void {
            $product->update($data);
            ProductVariants::sync($product, $variants);
            ProductImages::sync($product, $images);
        });

        return response()->json(['data' => $product->fresh()->load('category:id,name', 'variants', 'images')]);
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

    private function validated(Request $request, ?Product $product = null): array
    {
        $unique = Rule::unique('products')->ignore($product?->id);

        return $request->validate([
            'category_id' => [$product ? 'sometimes' : 'required', 'integer', 'exists:categories,id'],
            'name' => [$product ? 'sometimes' : 'required', 'string', 'max:160'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:180', 'alpha_dash', $unique],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'price_cents' => [$product ? 'sometimes' : 'required', 'integer', 'min:0'],
            'compare_at_price_cents' => ['sometimes', 'nullable', 'integer', 'min:0'],
            // Days after delivery the item can be returned; null = platform default, 0 = non-returnable.
            'return_days' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:'.SellerLedger::maxReturnDays()],
            'inventory_quantity' => ['sometimes', 'integer', 'min:0'],
            'image_url' => ['sometimes', 'nullable', 'string', 'max:500'],
            'is_active' => ['sometimes', 'boolean'],
            // Nothing existing fits — a free-text hint for admin to act on
            // manually; never becomes a real category on its own.
            'suggested_category_name' => ['sometimes', 'nullable', 'string', 'max:160'],

            // Full-replace gallery — see ProductImages::sync().
            'images' => ['sometimes', 'array', 'max:'.ProductImages::MAX_IMAGES],
            'images.*' => ['string', 'max:500'],

            'variants' => ['sometimes', 'array', 'max:'.(Sku::MAX_VARIANTS * 2)], // live + _delete rows; the real cap of 9 is enforced in ProductVariants::sync
            'variants.*.id' => ['sometimes', 'nullable', 'integer'],
            'variants.*._delete' => ['sometimes', 'boolean'],
            'variants.*.label' => ['required_with:variants', 'string', 'max:80'],
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
