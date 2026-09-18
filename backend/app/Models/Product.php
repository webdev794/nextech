<?php

namespace App\Models;

use App\Support\PublicMedia;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'name',
        'slug',
        'description',
        'sku',
        'price_cents',
        'compare_at_price_cents',
        'inventory_quantity',
        'image_url',
        'is_active',
        'deal_type',
        'is_exclusive_offer',
        'rating_avg',
        'rating_count',
        'units_sold',
    ];

    protected function casts(): array
    {
        return [
            'price_cents' => 'integer',
            'compare_at_price_cents' => 'integer',
            'inventory_quantity' => 'integer',
            'is_active' => 'boolean',
            'is_exclusive_offer' => 'boolean',
            'rating_avg' => 'float',
            'rating_count' => 'integer',
            'units_sold' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy('sort_order')->orderBy('id');
    }

    public function activeVariants(): HasMany
    {
        return $this->variants()->where('is_active', true);
    }

    public function hasVariants(): bool
    {
        return $this->activeVariants()->exists();
    }

    /** Per-store stock rows (base product + variants). */
    public function storeInventory(): HasMany
    {
        return $this->hasMany(StoreInventory::class);
    }

    /**
     * Does this product use per-store stock at all? False = "single stock" mode
     * (the product/variant inventory_quantity applies everywhere), which keeps
     * single-store installs and freshly created products working.
     */
    public function usesStoreInventory(): bool
    {
        return $this->relationLoaded('storeInventory')
            ? $this->storeInventory->isNotEmpty()
            : $this->storeInventory()->exists();
    }

    /**
     * Availability of one buyable line (base product, or a variant) at a store.
     *
     *  - no store in context, or the product isn't on per-store stock →
     *    "sold everywhere", quantity from the product/variant column;
     *  - on per-store stock → a stocked row is required; its quantity is
     *    authoritative and may be 0 ("out of stock" but still listed).
     *
     * @return array{sold: bool, quantity: int, per_store: bool}
     */
    public function availabilityAt(?int $storeId, ?ProductVariant $variant = null): array
    {
        $fallbackQty = (int) ($variant?->inventory_quantity ?? $this->inventory_quantity);

        if ($storeId === null || ! $this->usesStoreInventory()) {
            return ['sold' => true, 'quantity' => $fallbackQty, 'per_store' => false];
        }

        $row = $this->storeInventoryRow($storeId, $variant?->id);

        if (! $row || ! $row->is_stocked) {
            return ['sold' => false, 'quantity' => 0, 'per_store' => true];
        }

        return ['sold' => true, 'quantity' => (int) $row->quantity, 'per_store' => true];
    }

    public function storeInventoryRow(int $storeId, ?int $variantId): ?StoreInventory
    {
        if ($this->relationLoaded('storeInventory')) {
            return $this->storeInventory
                ->firstWhere(fn (StoreInventory $r) => $r->store_id === $storeId
                    && $r->product_variant_id === $variantId);
        }

        return $this->storeInventory()
            ->where('store_id', $storeId)
            ->where('product_variant_id', $variantId)
            ->first();
    }

    /**
     * Limit a query to products a customer at $storeId can see: those not on
     * per-store stock (single-stock mode), or with a stocked row at that store.
     * A null store applies no constraint. An out-of-stock (quantity 0) line
     * still counts as visible — the storefront greys it out.
     */
    public function scopeVisibleAtStore(Builder $query, ?int $storeId): Builder
    {
        if ($storeId === null) {
            return $query;
        }

        return $query->where(fn (Builder $q) => $q
            ->whereDoesntHave('storeInventory')
            ->orWhereHas('storeInventory', fn (Builder $r) => $r
                ->where('store_id', $storeId)
                ->where('is_stocked', true)));
    }

    public function getImageUrlAttribute(?string $value): ?string
    {
        return PublicMedia::url($value);
    }
}
