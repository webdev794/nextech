<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoreInventory extends Model
{
    protected $table = 'store_inventory';

    protected $fillable = [
        'store_id', 'product_id', 'product_variant_id', 'quantity', 'is_stocked',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'is_stocked' => 'boolean',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
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
