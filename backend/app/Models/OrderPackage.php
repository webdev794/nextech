<?php

namespace App\Models;

use App\Support\SellerShipping;
use App\Support\LiveTracking;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A package a seller shipped (own courier or NexTech-bought label) with its tracking. */
class OrderPackage extends Model
{
    /** Each package's tracking can be corrected at most this many times. */
    public const MAX_EDITS = 3;

    protected $fillable = [
        'order_id', 'shop_id', 'ship_from_address_id', 'label_source', 'carrier', 'tracking_number', 'label_url', 'label_path',
        'label_cost_cents', 'status', 'status_history', 'progress_updated_at', 'cash_collected_at', 'shipped_at', 'delivered_at', 'edit_count', 'last_edited_at',
        'carrier_name', 'tracking_site', 'delivery_code',
    ];

    // The delivery code is the buyer's — shown on their order, never to the seller.
    protected $hidden = ['label_path', 'delivery_code'];

    protected $appends = ['tracking_url', 'can_edit', 'has_label_file', 'tracking_label', 'carrier_label'];

    protected function casts(): array
    {
        return [
            'label_cost_cents' => 'integer',
            'edit_count' => 'integer',
            'shipped_at' => 'datetime',
            'delivered_at' => 'datetime',
            'status_history' => 'array',
            'progress_updated_at' => 'datetime',
            'cash_collected_at' => 'datetime',
            'last_edited_at' => 'datetime',
            'tracking_eta' => 'date',
            'tracking_events' => 'array',
            'tracking_synced_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderPackageItem::class);
    }

    /** Live courier status in words (LiveTracking), when the package is tracked. */
    public function getTrackingLabelAttribute(): ?string
    {
        return LiveTracking::label($this->tracking_tag);
    }

    public function getTrackingUrlAttribute(): ?string
    {
        // "Other carrier": the courier website the seller gave.
        if ($this->carrier === 'Other' && $this->tracking_site) {
            return $this->tracking_site;
        }

        return SellerShipping::trackingUrl($this->carrier, $this->tracking_number);
    }

    /** The courier's name as the buyer reads it. */
    public function getCarrierLabelAttribute(): string
    {
        return match (true) {
            $this->carrier === SellerShipping::LOCAL => 'Seller\'s own delivery',
            $this->carrier === 'Other' && $this->carrier_name => $this->carrier_name,
            default => \App\Support\Market::allCarriers()[$this->carrier][0] ?? (string) $this->carrier,
        };
    }

    /** An admin-uploaded label the seller downloads through the API. */
    public function getHasLabelFileAttribute(): bool
    {
        return $this->label_path !== null;
    }

    /** Tracking can be corrected until delivered/returned/lost, up to MAX_EDITS times. */
    public function getCanEditAttribute(): bool
    {
        return in_array($this->status, \App\Support\SellerProgress::MOVING, true)
            && $this->edit_count < self::MAX_EDITS
            && $this->label_source === 'own';
    }
}
