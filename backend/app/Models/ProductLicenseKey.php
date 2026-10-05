<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One license / activation key for a digital product, given to one buyer. Stored encrypted. */
class ProductLicenseKey extends Model
{
    protected $fillable = ['product_id', 'license_key', 'key_hash', 'order_item_id', 'assigned_at'];

    protected $hidden = ['license_key', 'key_hash'];

    protected function casts(): array
    {
        return ['license_key' => 'encrypted', 'assigned_at' => 'datetime'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
