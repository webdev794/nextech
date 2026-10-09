<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A rider's day off: asked for (leave) or missed without asking (absent). */
class RiderLeave extends Model
{
    protected $fillable = ['user_id', 'date', 'kind', 'told_ahead', 'reason'];

    protected function casts(): array
    {
        return ['date' => 'date', 'told_ahead' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
