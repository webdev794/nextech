<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A seller's signed acceptance of a policy page (typed name, date, the exact text version). */
class PolicyAcceptance extends Model
{
    protected $fillable = ['seller_id', 'page_id', 'page_version', 'signed_name', 'ip', 'accepted_at'];

    protected function casts(): array
    {
        return ['accepted_at' => 'datetime'];
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }
}
