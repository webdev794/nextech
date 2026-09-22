<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SellerLedgerEntry extends Model
{
    protected $fillable = [
        'shop_id', 'order_id', 'type', 'amount_cents', 'commission_cents', 'note', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'commission_cents' => 'integer',
        ];
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** The admin who recorded a payout_debit row. */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
