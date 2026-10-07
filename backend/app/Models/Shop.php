<?php

namespace App\Models;

use App\Support\Market;
use App\Support\PublicMedia;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shop extends Model
{
    protected $fillable = [
        'seller_id',
        'market',
        'commission_rate_bps',
        'name',
        'slug',
        'shop_code',
        'next_product_seq',
        'logo_url',
        'banner_url',
        'category_id',
        'description',
        'is_active',
        'fulfillment_mode',
        'accepts_cod',
        'cod_approved',
        'ships_saturday',
        'ships_sunday',
        'working_holidays',
        'intl_shipping',
        'free_shipping_accepted_at',
        'label_template_id',
        'local_delivery',
        'is_house',
        'intl_approval',
    ];

    protected function casts(): array
    {
        return [
            'decoration_terms_accepted_at' => 'datetime',
            'requirements' => 'array',
            'is_active' => 'boolean',
            'accepts_cod' => 'boolean',
            'cod_approved' => 'boolean',
            'next_product_seq' => 'integer',
            'ships_saturday' => 'boolean',
            'ships_sunday' => 'boolean',
            'working_holidays' => 'array',
            'intl_shipping' => 'array',
            'local_delivery' => 'array',
            'is_house' => 'boolean',
            'intl_approval' => 'array',
            'free_shipping_accepted_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // A shop sells in its seller's country; its products follow it.
        static::creating(function (Shop $shop): void {
            if (! $shop->isDirty('market')) {
                $shop->market = Market::forCountry($shop->seller?->country);
            }
        });
        static::updated(function (Shop $shop): void {
            if ($shop->wasChanged('market')) {
                $shop->products()->update(['market' => $shop->market]);
            }
        });
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** Store page designs (Store decoration), per platform. */
    public function decorations(): HasMany
    {
        return $this->hasMany(StoreDecoration::class);
    }

    /** Trademarks the shop registered (Account health). */
    public function trademarks(): HasMany
    {
        return $this->hasMany(Trademark::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(SellerLedgerEntry::class);
    }

    public function payoutRequests(): HasMany
    {
        return $this->hasMany(PayoutRequest::class);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(ShopAddress::class);
    }

    public function shippingTemplates(): HasMany
    {
        return $this->hasMany(ShippingTemplate::class);
    }

    /** Ships its own orders (own courier or a NexTech-bought label) rather than NexTech collecting them. */
    public function shipsItself(): bool
    {
        return in_array($this->fulfillment_mode, ['self', 'label'], true);
    }

    /**
     * Its shipping terms to another country's buyers — ['fee_cents' (shop
     * currency), 'transit_min_days', 'transit_max_days'] — or null when it
     * doesn't ship there. Only sellers shipping with their own courier can.
     */
    public function shipsTo(string $market): ?array
    {
        if ($this->fulfillment_mode !== 'self' || strtoupper($market) === $this->market || ! \App\Support\SellerIntl::allowed($this)) {
            return null;
        }
        $terms = ((array) $this->intl_shipping)[strtoupper($market)] ?? null;

        return is_array($terms) && in_array(strtoupper($market), Market::codes(), true) ? $terms : null;
    }

    /** Running balance owed to this shop — always summed from the ledger, never a stored column. */
    public function balanceCents(): int
    {
        return (int) $this->ledgerEntries()->sum('amount_cents');
    }

    public function getLogoUrlAttribute(?string $value): ?string
    {
        return PublicMedia::url($value);
    }

    public function getBannerUrlAttribute(?string $value): ?string
    {
        return PublicMedia::url($value);
    }
}
