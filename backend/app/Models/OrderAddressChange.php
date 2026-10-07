<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderAddressChange extends Model
{
    protected $fillable = ['order_id', 'address', 'status', 'note', 'decided_by_shop_id', 'decided_by_user_id', 'decided_at'];

    protected function casts(): array
    {
        return [
            'address' => 'array',
            'decided_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
