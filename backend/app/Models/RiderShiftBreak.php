<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiderShiftBreak extends Model
{
    protected $fillable = ['rider_shift_id', 'reason', 'started_at', 'ended_at'];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(RiderShift::class, 'rider_shift_id');
    }
}
