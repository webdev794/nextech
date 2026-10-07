<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A self-shipping seller's warehouse / ship-from address. */
class ShopAddress extends Model
{
    protected $fillable = [
        'shop_id', 'name', 'line1', 'line2', 'city', 'state', 'postal_code', 'country', 'phone', 'contact_name', 'is_default',
    ];

    protected function casts(): array
    {
        return ['is_default' => 'boolean'];
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    /** @return array<string, mixed> In the shape the courier provider expects. */
    public function toCourierAddress(): array
    {
        return [
            'phone' => $this->phone,
            'line1' => $this->line1,
            'line2' => $this->line2,
            'city' => $this->city,
            'state' => $this->state,
            'postal_code' => $this->postal_code,
            'country' => $this->country,
        ];
    }
}
