<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GiftCardRedemption extends Model
{
    protected $fillable = ['gift_card_id', 'order_id', 'amount_cents', 'reversed_at'];

    protected function casts(): array
    {
        return ['amount_cents' => 'integer', 'reversed_at' => 'datetime'];
    }

    public function giftCard(): BelongsTo
    {
        return $this->belongsTo(GiftCard::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
