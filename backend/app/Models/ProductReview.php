<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A buyer's review of a product they received; shown publicly once admin approves it. */
class ProductReview extends Model
{
    protected $fillable = ['user_id', 'product_id', 'order_id', 'order_item_id', 'variant_label', 'rating', 'body', 'fit', 'images', 'status', 'show_on_profile', 'admin_note', 'reviewed_at', 'helpful_count'];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'images' => 'array',
            'show_on_profile' => 'boolean',
            'reviewed_at' => 'datetime',
            'helpful_count' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function votes(): HasMany
    {
        return $this->hasMany(ReviewHelpfulVote::class);
    }
}
