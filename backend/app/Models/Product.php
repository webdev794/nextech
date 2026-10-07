<?php

namespace App\Models;

use App\Support\Market;
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
        'shop_id',
        'market',
        'product_type',
        'digital_settings',
        'name',
        'slug',
        'description',
        'sku',
        'hsn_code',
        'gst_rate_bps',
        'country_of_origin',
        'manufacturer_info',
        'next_variant_seq',
        'price_cents',
        'compare_at_price_cents',
        'return_days',
        'return_policy',
        'shipping_template_id',
        'inventory_quantity',
        'image_url',
        'video_url',
        'is_active',
        'deactivated_by',
        'ships_abroad',
        'intl_extra_fee_cents',
        'deletion_requested_at',
        'deletion_reason',
        'archived_at',
        'deal_type',
        'is_exclusive_offer',
        'is_demo',
        'affiliate_url',
        'affiliate_merchant',
        'condition',
        'lightning_starts_at',
        'lightning_ends_at',
        'lightning_qty',
        'lightning_base_sold',
        'rating_avg',
        'rating_count',
        'units_sold',
        'status',
        'rejection_reason',
        'followup_items',
        'followup_requested_at',
        'suggested_category_name',
        // Seller listing (Temu-style Add product; see App\Support\ProductCatalog).
        'seller_code', 'trademark_id', 'bullet_points', 'detail_images', 'detail_video_url', 'product_details',
        'variation_theme', 'size_chart', 'handling_days', 'compliance', 'price_references', 'personalization', 'info_sections', 'guides',
    ];

    protected function casts(): array
    {
        return [
            'return_policy' => 'array',
            'price_cents' => 'integer',
            'gst_rate_bps' => 'integer',
            'next_variant_seq' => 'integer',
            'compare_at_price_cents' => 'integer',
            'inventory_quantity' => 'integer',
            'is_active' => 'boolean',
            'is_exclusive_offer' => 'boolean',
            'is_demo' => 'boolean',
            'affiliate_clicks' => 'integer',
            'lightning_starts_at' => 'datetime',
            'lightning_ends_at' => 'datetime',
            'rating_avg' => 'float',
            'rating_count' => 'integer',
            'units_sold' => 'integer',
            'bullet_points' => 'array',
            'detail_images' => 'array',
            'product_details' => 'array',
            'followup_items' => 'array',
            'followup_requested_at' => 'datetime',
            'variation_theme' => 'array',
            'size_chart' => 'array',
            'handling_days' => 'integer',
            'compliance' => 'array',
            'personalization' => 'array',
            'info_sections' => 'array',
            'guides' => 'array',
            'pending_changes' => 'array',
            'ships_abroad' => 'boolean',
            'pending_submitted_at' => 'datetime',
            'digital_settings' => 'array',
            'price_references' => 'array',
        ];
    }

    protected static function booted(): void
    {
        // A seller product's market is its shop's; NexTech's own products are
        // in the market the admin created them in.
        static::saving(function (Product $product): void {
            // Return conditions are stored tidy (known keys only; null when empty).
            if ($product->isDirty('return_policy')) {
                $product->return_policy = \App\Support\ReturnPolicy::clean($product->return_policy);
            }
            // Seller products follow their shop; NexTech's own keep the market
            // the admin created them in (default: home).
            if ($product->shop_id && ($product->isDirty('shop_id') || ! $product->exists)) {
                $product->market = Shop::find($product->shop_id)?->market ?? Market::home();
            } elseif (! $product->shop_id && ! $product->exists && ! $product->isDirty('market')) {
                $product->market = Market::home();
            }
        });
    }

    /** Admin has hidden all demo products from the store (Products -> "Demo products"). */
    public static function demosHidden(): bool
    {
        return (bool) Setting::get('hide_demo_products', false);
    }

    /** Admin has hidden all affiliate products from the store (Products → "Affiliate products"). */
    public static function affiliatesHidden(): bool
    {
        return (bool) Setting::get('hide_affiliate_products', false);
    }

    /**
     * Not shown or sold to shoppers: a demo product while demos are hidden, an
     * affiliate product while those are hidden, or
     * a seller's product while their shop isn't live (seller suspended or
     * removed, shop switched off).
     */
    public function hiddenFromShoppers(): bool
    {
        if ($this->is_demo && self::demosHidden()) {
            return true;
        }
        if ($this->affiliate_url && self::affiliatesHidden()) {
            return true;
        }
        if ($this->shop_id === null) {
            return false;
        }
        $shop = $this->shop;

        return ! ($shop?->is_active && $shop->seller?->status === 'approved');
    }

    /** What shoppers may see: NexTech's own products and live shops' ones, no demo products while they're hidden. */
    public function scopeShownToShoppers(Builder $query): Builder
    {
        return $query
            ->when(self::demosHidden(), fn ($q) => $q->where($q->qualifyColumn('is_demo'), false))
            ->when(self::affiliatesHidden(), fn ($q) => $q->whereNull($q->qualifyColumn('affiliate_url')))
            ->where(fn ($q) => $q->whereNull($q->qualifyColumn('shop_id'))
                ->orWhereHas('shop', fn ($shop) => $shop->where('is_active', true)->whereHas('seller', fn ($seller) => $seller->where('status', 'approved'))));
    }

    /** Only products sold in this market. */
    public function scopeInMarket(Builder $query, string $market): Builder
    {
        return $query->where($query->qualifyColumn('market'), $market);
    }

    /**
     * Products a shopper in this market can buy: the market's own, plus those
     * of other countries' sellers who ship here (Shop::shipsTo).
     */
    public function scopeAvailableIn(Builder $query, string $market): Builder
    {
        $market = strtoupper($market);

        return $query->where(fn ($q) => $q->where($q->qualifyColumn('market'), $market)
            ->orWhere(fn ($abroad) => $abroad
                ->where(fn ($p) => $p->where($q->qualifyColumn('ships_abroad'), true)->orWhere($q->qualifyColumn('product_type'), 'digital'))
                ->whereHas('shop', fn ($shop) => $shop->where('fulfillment_mode', 'self')
                    ->where('market', '!=', $market)
                    ->whereNotNull("intl_shipping->{$market}"))));
    }

    /** Shipped in from another country for a buyer in $market: the shop's terms for it, else null. */
    public function crossBorderTerms(string $market): ?array
    {
        if ($this->shop_id === null || $this->market === strtoupper($market)) {
            return null;
        }
        // The seller chose to sell this one only in their own country.
        if (! $this->isDigital() && $this->ships_abroad === false) {
            return null;
        }
        $shop = $this->relationLoaded('shop') ? $this->shop : $this->shop()->first();

        return $shop?->shipsTo($market);
    }

    /** Downloaded instead of shipped (games, software, e-books…). */
    public function isDigital(): bool
    {
        return $this->product_type === 'digital';
    }

    public function files(): HasMany
    {
        return $this->hasMany(ProductFile::class)->orderBy('sort_order')->orderBy('id');
    }

    public function licenseKeys(): HasMany
    {
        return $this->hasMany(ProductLicenseKey::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** The third-party shop this product belongs to, or null = sold directly by NexTech. */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy('sort_order')->orderBy('id');
    }

    public function activeVariants(): HasMany
    {
        return $this->variants()->where('is_active', true);
    }

    /** The brand a seller listed it under (an approved trademark). */
    public function trademark(): BelongsTo
    {
        return $this->belongsTo(Trademark::class);
    }

    /** Recommended prices NexTech offered while the product is "Low traffic". */
    public function salesBoostOffers(): HasMany
    {
        return $this->hasMany(SalesBoostOffer::class);
    }

    /** The product's multi-image gallery, in display order. */
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order')->orderBy('id');
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

    public function getVideoUrlAttribute(?string $value): ?string
    {
        return PublicMedia::url($value);
    }
}
