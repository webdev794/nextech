<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A trademark a shop registered (Account health) for NexTech to review; approved ones can go on products. */
class Trademark extends Model
{
    protected $fillable = ['shop_id', 'name', 'registration_number', 'registration_country', 'logo_url', 'certificate_path', 'status', 'note', 'reviewed_at'];

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime'];
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }
}
