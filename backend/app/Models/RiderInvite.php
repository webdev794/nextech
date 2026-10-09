<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A rider with no active store suggested to a store near home: the store invites, the rider accepts. */
class RiderInvite extends Model
{
    protected $fillable = ['store_id', 'user_id', 'status'];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
