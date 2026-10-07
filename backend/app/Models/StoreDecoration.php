<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One version of a shop's store page design, for desktop or mobile (App\Support\StoreDecorations). */
class StoreDecoration extends Model
{
    protected $fillable = ['shop_id', 'platform', 'name', 'status', 'is_live', 'page', 'sections', 'review_note', 'submitted_at', 'reviewed_at', 'published_at'];

    protected function casts(): array
    {
        return [
            'is_live' => 'boolean',
            'page' => 'array',
            'sections' => 'array',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }
}
