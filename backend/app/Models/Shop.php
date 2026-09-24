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
        'ships_saturday',
        'ships_sunday',
        'working_holidays',
        'free_shipping_accepted_at',
        'label_template_id',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'next_product_seq' => 'integer',
            'ships_saturday' => 'boolean',
            'ships_sunday' => 'boolean',
            'working_holidays' => 'array',
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
