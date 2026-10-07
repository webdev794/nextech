<?php

namespace App\Models;

use App\Support\PublicMedia;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductVariant extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id', 'label', 'sku', 'price_cents', 'compare_at_price_cents',
        'inventory_quantity', 'image_url', 'sort_order', 'is_active',
        'options', 'seller_code', 'weight_grams', 'length_mm', 'width_mm', 'height_mm',
    ];

    protected function casts(): array
    {
        return [
            'price_cents' => 'integer',
            'compare_at_price_cents' => 'integer',
            'inventory_quantity' => 'integer',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
            'options' => 'array',
            'weight_grams' => 'integer',
            'length_mm' => 'integer',
            'width_mm' => 'integer',
            'height_mm' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function getImageUrlAttribute(?string $value): ?string
    {
        return PublicMedia::url($value);
    }
}
