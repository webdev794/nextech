<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\Purchasable;
use App\Support\StoreLocator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CartController extends Controller
{
    /**
     * Serving store id from an optional lat/lng on the request, so cart stock
     * checks use that store's shelf. Null → the product's single stock.
     *
     * @param  array<string, mixed>  $validated
     */
    private function servingStoreId(array $validated): ?int
    {
        return StoreLocator::servingStore(
            isset($validated['lat']) ? (float) $validated['lat'] : null,
            isset($validated['lng']) ? (float) $validated['lng'] : null,
        )?->id;
    }

    public function show(Request $request): JsonResponse
    {
        $cart = $this->cartFor($request);

        return response()->json($this->payload($cart));
    }

    public function addItem(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'product_variant_id' => ['sometimes', 'nullable', 'integer', 'exists:product_variants,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:1000'],
            'lat' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'lng' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
        ]);

        $storeId = $this->servingStoreId($validated);
        $cart = $this->cartFor($request);

        DB::transaction(function () use ($cart, $validated, $storeId): void {
            $product = Product::query()->whereKey($validated['product_id'])->lockForUpdate()->firstOrFail();
            $variant = $this->resolveVariant($product, $validated['product_variant_id'] ?? null, true);

            $state = Purchasable::resolve($product, $variant, $storeId);
            $this->ensurePurchasable($state);

            $existing = $cart->items()
                ->where('product_id', $product->id)
                ->where('product_variant_id', $variant?->id)
                ->lockForUpdate()
                ->first();
            $quantity = ($existing?->quantity ?? 0) + $validated['quantity'];

            $this->ensureStock($state, $quantity);

            $cart->items()->updateOrCreate(
                ['product_id' => $product->id, 'product_variant_id' => $variant?->id],
                ['quantity' => $quantity, 'unit_price_cents' => $state['price_cents']],
            );
        });

        return response()->json($this->payload($cart->fresh()), 201);
    }

    public function updateItem(Request $request, CartItem $cartItem): JsonResponse
    {
        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:1000'],
            'lat' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'lng' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
        ]);

        $storeId = $this->servingStoreId($validated);
        $cart = $this->cartFor($request);
        abort_unless($cartItem->cart_id === $cart->id, 404);

        DB::transaction(function () use ($cartItem, $validated, $storeId): void {
            $product = Product::query()->whereKey($cartItem->product_id)->lockForUpdate()->firstOrFail();
            $variant = $cartItem->product_variant_id
                ? ProductVariant::query()->whereKey($cartItem->product_variant_id)->lockForUpdate()->first()
                : null;

            $state = Purchasable::resolve($product, $variant, $storeId);
            $this->ensurePurchasable($state);
            $this->ensureStock($state, $validated['quantity']);

            $cartItem->update([
                'quantity' => $validated['quantity'],
                'unit_price_cents' => $state['price_cents'],
            ]);
        });

        return response()->json($this->payload($cart->fresh()), 200);
    }

    public function removeItem(Request $request, CartItem $cartItem): JsonResponse
    {
        $cart = $this->cartFor($request);
        abort_unless($cartItem->cart_id === $cart->id, 404);

        $cartItem->delete();

        return response()->json($this->payload($cart->fresh()), 200);
    }

    public function clear(Request $request): JsonResponse
    {
        $cart = $this->cartFor($request);
        $cart->items()->delete();

        return response()->json($this->payload($cart->fresh()), 200);
    }

    private function cartFor(Request $request): Cart
    {
        return Cart::firstOrCreate(['user_id' => $request->user()->id]);
    }

    /**
     * Resolve the requested variant. Null is allowed — it means the base
     * product option. When an id is given it must be an active variant of this
     * product.
     */
    private function resolveVariant(Product $product, ?int $variantId, bool $lock = false): ?ProductVariant
    {
        if ($variantId === null) {
            return null;
        }

        $query = $product->variants()->whereKey($variantId)->where('is_active', true);
        $variant = $lock ? $query->lockForUpdate()->first() : $query->first();

        if (! $variant) {
            throw ValidationException::withMessages([
                'product_variant_id' => ['That option is not available.'],
            ]);
        }

        return $variant;
    }

    private function payload(Cart $cart): array
    {
        $cart->load(['items.product.category', 'items.productVariant']);
        $items = $cart->items->map(function (CartItem $item): array {
            return [
                'id' => $item->id,
                'quantity' => $item->quantity,
                'unit_price_cents' => $item->unit_price_cents,
                'line_total_cents' => $item->quantity * $item->unit_price_cents,
                'product' => $item->product,
                'product_variant' => $item->productVariant,
            ];
        })->values();

        return [
            'data' => [
                'id' => $cart->id,
                'items' => $items,
                'item_count' => $items->sum('quantity'),
                'subtotal_cents' => $items->sum('line_total_cents'),
            ],
        ];
    }

    /**
     * @param  array{active: bool, ...}  $state
     */
    private function ensurePurchasable(array $state): void
    {
        if (! $state['active']) {
            throw ValidationException::withMessages([
                'product_id' => ['This product is not available.'],
            ]);
        }
    }

    /**
     * @param  array{inventory_quantity: int, ...}  $state
     */
    private function ensureStock(array $state, int $quantity): void
    {
        if ($quantity > $state['inventory_quantity']) {
            throw ValidationException::withMessages([
                'quantity' => ['The requested quantity is not available.'],
            ]);
        }
    }
}
