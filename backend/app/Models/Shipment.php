<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Shipment extends Model
{
    protected $fillable = [
        'order_id',
        'provider',
        'tracking_number',
        'carrier',
        'label_url',
        'status',
        'cost_cents',
        'booked_at',
    ];

    protected function casts(): array
    {
        return [
            'cost_cents' => 'integer',
            'booked_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
