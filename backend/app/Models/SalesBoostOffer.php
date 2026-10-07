<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A recommended price NexTech offers for a "Low traffic" product (or one of
 * its variations). The seller accepts it (the price changes) or rejects it
 * (that variation is closed).
 */
class SalesBoostOffer extends Model
{
    protected $fillable = ['product_id', 'product_variant_id', 'current_price_cents', 'recommended_price_cents', 'status', 'created_by', 'decided_at'];

    protected function casts(): array
    {
        return [
            'current_price_cents' => 'integer',
            'recommended_price_cents' => 'integer',
            'decided_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
