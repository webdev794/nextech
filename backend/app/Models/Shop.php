<?php

namespace App\Models;

use App\Support\PublicMedia;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shop extends Model
{
    protected $fillable = [
        'seller_id',
        'name',
        'slug',
        'shop_code',
        'next_product_seq',
        'logo_url',
        'banner_url',
        'category_id',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'next_product_seq' => 'integer',
        ];
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
