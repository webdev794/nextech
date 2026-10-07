<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiderLedgerEntry extends Model
{
    protected $fillable = [
        'user_id', 'order_id', 'type', 'amount_cents', 'distance_miles', 'note', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'distance_miles' => 'float',
        ];
    }

    public function rider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
