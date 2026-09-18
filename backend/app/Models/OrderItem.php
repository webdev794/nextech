<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id', 'product_id', 'product_variant_id', 'product_name', 'sku',
        'variant_label', 'quantity', 'unit_price_cents', 'compare_at_price_cents', 'line_total_cents',
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
}