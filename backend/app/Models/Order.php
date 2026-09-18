<?php

namespace App\Models;

use App\Notifications\OrderDelivered;
use App\Support\Geo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    use HasFactory;

    /**
     * Delivery states an administrator may move a paid order through, and the
     * states each one may advance to. Steps cannot be skipped, an order can be
     * cancelled up until it leaves the store, and completed or cancelled orders
     * are terminal.
     */
    public const DELIVERY_TRANSITIONS = [
        // A card order whose payment was never completed can only be cancelled.
        'pending_payment' => ['cancelled'],
        'confirmed' => ['packing', 'cancelled'],
        'packing' => ['ready_for_delivery', 'cancelled'],
        'ready_for_delivery' => ['out_for_delivery', 'cancelled'],
        'out_for_delivery' => ['completed'],
        'completed' => [],
        'cancelled' => [],
    ];

    protected $fillable = [
        'user_id', 'store_id', 'status', 'cancelled_by', 'cancel_reason', 'courier_name', 'payment_status', 'payment_method',
        'subtotal_cents', 'tax_cents', 'delivery_fee_cents', 'handling_fee_cents',
        'small_cart_fee_cents', 'gift_card_discount_cents', 'total_cents', 'delivery_address', 'delivery_instructions',
        'stripe_payment_intent_id', 'stripe_refund_id', 'refunded_amount_cents',
        'delivery_partner_id',
        'rider_offer_expires_at', 'rider_accepted_at', 'rider_offer_declined_ids', 'rider_offer_decline_count',
        'delivered_at', 'cash_settled_at', 'cash_collected_at', 'items_returned_at', 'delivery_verified', 'delivery_note',
        'delivery_code', 'delivery_code_expires_at', 'receipt_emailed_at',
    ];

    /** A Stripe dashboard link for support/audit; the payment page shows the refund. */
    protected $appends = ['stripe_dashboard_url'];

    /** The handover code is surfaced only to the owning customer, explicitly. */
    protected $hidden = ['delivery_code', 'delivery_code_expires_at'];

    protected function casts(): array
    {
        return [
            'subtotal_cents' => 'integer',
            'tax_cents' => 'integer',
            'delivery_fee_cents' => 'integer',
            'handling_fee_cents' => 'integer',
            'small_cart_fee_cents' => 'integer',
            'gift_card_discount_cents' => 'integer',
            'total_cents' => 'integer',
            'refunded_amount_cents' => 'integer',
            'delivery_address' => 'array',
            'rider_offer_expires_at' => 'datetime',
            'rider_accepted_at' => 'datetime',
            'rider_offer_declined_ids' => 'array',
            'rider_offer_decline_count' => 'integer',
            'delivered_at' => 'datetime',
            'cash_settled_at' => 'datetime',
            'cash_collected_at' => 'datetime',
            'items_returned_at' => 'datetime',
            'delivery_verified' => 'boolean',
            'delivery_code_expires_at' => 'datetime',
            'receipt_emailed_at' => 'datetime',
        ];
    }

    /**
     * Email the customer their order summary + PDF bill, but only once the order
     * is both delivered and paid. Idempotent: stamps receipt_emailed_at so it
     * never fires twice, and safe to call from every state-changing endpoint.
     */
    public function sendDeliveredReceiptIfReady(): void
    {
        if ($this->receipt_emailed_at !== null
            || $this->status !== 'completed'
            || $this->payment_status !== 'paid') {
            return;
        }

        $this->loadMissing('user');
        if (! $this->user?->email) {
            return;
        }

        try {
            $this->user->notify(new OrderDelivered($this));
            $this->forceFill(['receipt_emailed_at' => now()])->saveQuietly();
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /** True while a rider assignment is still an unaccepted, time-boxed offer. */
    public function hasLiveOffer(): bool
    {
        return $this->rider_offer_expires_at !== null && $this->rider_accepted_at === null;
    }

    public function deliveryCodeActive(): bool
    {
        return $this->delivery_code !== null
            && $this->delivery_code_expires_at !== null
            && $this->delivery_code_expires_at->isFuture();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function deliveryPartner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delivery_partner_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(OrderRefund::class);
    }

    public function giftCards(): HasMany
    {
        return $this->hasMany(GiftCard::class);
    }

    /** Gift-card spend applied to this order at checkout (not cards issued for it). */
    public function giftCardRedemptions(): HasMany
    {
        return $this->hasMany(GiftCardRedemption::class);
    }

    /**
     * Cancelling an order voids any gift-card balance it spent at checkout —
     * the card is credited back and the redemption marked reversed, so it
     * can't be restored twice.
     */
    public function restoreGiftCardRedemptions(): void
    {
        $this->giftCardRedemptions()->whereNull('reversed_at')->get()->each(function (GiftCardRedemption $redemption) {
            $card = GiftCard::whereKey($redemption->gift_card_id)->lockForUpdate()->first();
            if (! $card) {
                return;
            }

            $card->increment('balance_cents', $redemption->amount_cents);
            if (! $card->is_active && $card->fresh()->balance_cents > 0) {
                $card->update(['is_active' => true]);
            }
            $redemption->update(['reversed_at' => now()]);
        });
    }

    /**
     * True when a gift card covered the entire order — no Stripe charge or
     * COD cash ever changed hands, so restoring the gift card balance on
     * cancellation already is the full refund; nothing for an admin to action.
     */
    public function wasFullyCoveredByGiftCard(): bool
    {
        return $this->gift_card_discount_cents > 0 && $this->total_cents <= 0;
    }

    /**
     * A rider cancelled this at the door (customer refused to pay) — the
     * bagged items never made it to the customer, so they're still with the
     * rider until the store confirms they came back.
     */
    public function needsItemReturn(): bool
    {
        return $this->status === 'cancelled'
            && $this->cancelled_by === 'rider'
            && $this->items_returned_at === null;
    }

    public function riderReview(): HasOne
    {
        return $this->hasOne(RiderReview::class);
    }

    public function supportThreads(): HasMany
    {
        return $this->hasMany(SupportThread::class);
    }

    /** Store credit already issued against this order as a gift-card refund. */
    public function giftCardRefundedCents(): int
    {
        return (int) $this->giftCards()->sum('initial_cents');
    }

    public function refundableRemainingCents(): int
    {
        return max(0, (int) $this->total_cents - (int) $this->refunded_amount_cents);
    }

    public function isCashOnDelivery(): bool
    {
        return $this->payment_method === 'cod';
    }

    public function getStripeDashboardUrlAttribute(): ?string
    {
        if (! $this->stripe_payment_intent_id) {
            return null;
        }

        $prefix = str_starts_with((string) config('services.stripe.secret'), 'sk_test_') ? 'test/' : '';

        return "https://dashboard.stripe.com/{$prefix}payments/{$this->stripe_payment_intent_id}";
    }

    /**
     * The shop the bill is issued from. Prefer the store recorded at checkout
     * (`store_id`); for older orders placed before that column existed, fall
     * back to the active store nearest the delivery address, then to the first
     * active store. Null when no stores are configured.
     */
    public function fulfillingStore(): ?Store
    {
        if ($this->store_id) {
            return $this->relationLoaded('store') ? $this->store : $this->store()->first();
        }

        $stores = Store::query()->where('is_active', true)->orderBy('id')->get();

        if ($stores->isEmpty()) {
            return null;
        }

        $lat = $this->delivery_address['latitude'] ?? null;
        $lng = $this->delivery_address['longitude'] ?? null;

        if ($lat !== null && $lng !== null) {
            $nearest = Geo::nearestStore($stores, (float) $lat, (float) $lng);
            if ($nearest) {
                return $nearest['store'];
            }
        }

        return $stores->first();
    }

    public function canTransitionTo(string $status): bool
    {
        if (! in_array($status, self::DELIVERY_TRANSITIONS[$this->status] ?? [], true)) {
            return false;
        }

        // Cancelling needs no payment: it's the only move for an order whose
        // card payment was never completed.
        if ($status === 'cancelled') {
            return true;
        }

        // A cash-on-delivery order is collected on hand-off, so it may move
        // through the delivery states before payment_status becomes 'paid'.
        return $this->payment_status === 'paid' || $this->isCashOnDelivery();
    }
}
