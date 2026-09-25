<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id', 'product_id', 'product_variant_id', 'shop_id', 'fulfilled_by', 'product_name', 'sku', 'hsn_code', 'gst_rate_bps',
        'variant_label', 'quantity', 'unit_price_cents', 'compare_at_price_cents', 'return_days', 'line_total_cents',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price_cents' => 'integer',
            'compare_at_price_cents' => 'integer',
            'line_total_cents' => 'integer',
        ];
    }

    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    public function productVariant(): BelongsTo { return $this->belongsTo(ProductVariant::class); }
    public function packageItems(): \Illuminate\Database\Eloquent\Relations\HasMany { return $this->hasMany(OrderPackageItem::class); }
    public function shop(): BelongsTo { return $this->belongsTo(Shop::class); }
    /** The buyer's review of this item, if they wrote one. */
    public function review(): \Illuminate\Database\Eloquent\Relations\HasOne { return $this->hasOne(ProductReview::class); }
}