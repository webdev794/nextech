<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A digital product's downloadable file (private storage) or seller-hosted link. */
class ProductFile extends Model
{
    protected $fillable = ['product_id', 'name', 'original_name', 'path', 'external_url', 'link_status', 'link_note', 'link_checked_at', 'size_bytes', 'sort_order'];

    protected $hidden = ['path', 'external_url'];

    protected function casts(): array
    {
        return ['size_bytes' => 'integer', 'sort_order' => 'integer'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
