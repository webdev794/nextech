<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** What a self/label-shipping seller charged on an order, and the delivery promise. */
class OrderShopShipping extends Model
{
    protected $table = 'order_shop_shipping';

    protected $fillable = [
        'order_id', 'shop_id', 'mode', 'fee_cents', 'seller_fee_cents', 'paperwork_cents', 'seller_paperwork_cents', 'free_shipping', 'transit_min_days', 'transit_max_days',
        'ship_by', 'deliver_from', 'deliver_by', 'packed_at', 'method',
        'reminded_at', 'reminder_count', 'escalated_at', 'rider_offer_until', 'rider_offer_missed_at', 'local_km',
    ];

    protected function casts(): array
    {
        return [
            'fee_cents' => 'integer',
            'free_shipping' => 'boolean',
            'ship_by' => 'date',
            'packed_at' => 'datetime',
            'reminded_at' => 'datetime',
            'reminder_count' => 'integer',
            'escalated_at' => 'datetime',
            'rider_offer_until' => 'datetime',
            'local_km' => 'float',
            'rider_offer_missed_at' => 'datetime',
            'deliver_from' => 'date',
            'deliver_by' => 'date',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }
}
