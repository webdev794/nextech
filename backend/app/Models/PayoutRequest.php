<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A seller asking to be paid out once their balance reaches the minimum. */
class PayoutRequest extends Model
{
    protected $fillable = [
        'shop_id', 'amount_cents', 'status', 'admin_note', 'ledger_entry_id', 'processed_by', 'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'processed_at' => 'datetime',
        ];
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }
}
