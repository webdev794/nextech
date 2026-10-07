<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A rider asking to be paid their earnings (net of any COD cash they still hold). */
class RiderPayoutRequest extends Model
{
    protected $fillable = [
        'user_id', 'amount_cents', 'status', 'admin_note', 'ledger_entry_id', 'processed_by', 'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'processed_at' => 'datetime',
        ];
    }

    public function rider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
