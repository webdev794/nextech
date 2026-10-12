<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A rider's day off: asked for (leave) or missed without asking (absent). */
class RiderLeave extends Model
{
    protected $fillable = ['user_id', 'date', 'new_date', 'change_reason', 'kind', 'status', 'told_ahead', 'reason', 'decision_note', 'decided_by', 'decided_at'];

    protected function casts(): array
    {
        return ['date' => 'date', 'new_date' => 'date', 'told_ahead' => 'boolean', 'decided_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
