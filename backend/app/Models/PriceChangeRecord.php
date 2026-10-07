<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A price the seller changed by accepting a sales boost offer (Pricing records). */
class PriceChangeRecord extends Model
{
    protected $fillable = ['product_id', 'product_variant_id', 'old_price_cents', 'new_price_cents', 'source', 'sales_boost_offer_id'];

    protected function casts(): array
    {
        return ['old_price_cents' => 'integer', 'new_price_cents' => 'integer'];
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
