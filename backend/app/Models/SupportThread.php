<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportThread extends Model
{
    public const ISSUE_TYPES = [
        'item_missing', 'item_damaged', 'wrong_item', 'not_delivered', 'payment_issue', 'other',
        // Opened by the delivery rider, not chosen by the customer.
        'delivery',
        // Seller <-> admin channel, not a customer order complaint.
        'seller_product_issue', 'seller_other',
    ];

    protected $fillable = [
        'user_id', 'order_id', 'seller_shop_id', 'seller_joined_at', 'issue_type', 'status',
        'last_message_at', 'last_staff_message_at', 'resolved_at',
    ];

    protected $appends = ['needs_reply'];

    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
            'last_staff_message_at' => 'datetime',
            'resolved_at' => 'datetime',
            'seller_joined_at' => 'datetime',
            'rating' => 'integer',
            'rated_at' => 'datetime',
        ];
    }

    /** True when the newest message is from the customer and staff hasn't replied since. */
    public function getNeedsReplyAttribute(): bool
    {
        return $this->last_message_at !== null
            && ($this->last_staff_message_at === null || $this->last_message_at->gt($this->last_staff_message_at));
    }

    /** A conversation is rateable once staff have actually taken part — an
     *  internal-only note doesn't count, since the customer never saw it. */
    public function hasStaffReply(): bool
    {
        return $this->last_staff_message_at !== null
            || $this->messages()->where('is_staff', true)->where('internal', false)->exists();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** The seller shop an admin brought into this order chat, if any. */
    public function sellerShop(): BelongsTo
    {
        return $this->belongsTo(Shop::class, 'seller_shop_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SupportMessage::class)->orderBy('id');
    }

    /**
     * @param  bool  $internal  A staff-only note (e.g. why a refund was issued) —
     *                          recorded for later admin reference but never shown
     *                          to the customer, and doesn't count as a reply.
     */
    /**
     * @param  list<string>  $attachments  photo URLs from POST /support/attachments
     */
    public function post(?User $sender, string $body, bool $isStaff = false, bool $system = false, bool $internal = false, bool $fromSeller = false, array $attachments = [], bool $hiddenFromSeller = false): SupportMessage
    {
        $message = $this->messages()->create([
            'user_id' => $system ? null : $sender?->id,
            'is_staff' => $isStaff,
            'from_seller' => $fromSeller,
            'internal' => $internal,
            'hidden_from_seller' => $hiddenFromSeller,
            'body' => $body,
            'attachments' => $attachments ?: null,
        ]);

        if (! $internal) {
            $changes = ['last_message_at' => $message->created_at];
            if ($isStaff) {
                $changes['last_staff_message_at'] = $message->created_at;
            }
            $this->forceFill($changes)->save();
        }

        return $message;
    }
}
