<?php

namespace App\Models;

use App\Support\SellerShipping;
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
        'label_cost_cents', 'status', 'shipped_at', 'delivered_at', 'edit_count', 'last_edited_at',
    ];

    protected $hidden = ['label_path'];

    protected $appends = ['tracking_url', 'can_edit', 'has_label_file'];

    protected function casts(): array
    {
        return [
            'label_cost_cents' => 'integer',
            'edit_count' => 'integer',
            'shipped_at' => 'datetime',
            'delivered_at' => 'datetime',
            'last_edited_at' => 'datetime',
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

    public function getTrackingUrlAttribute(): ?string
    {
        return SellerShipping::trackingUrl($this->carrier, $this->tracking_number);
    }

    /** An admin-uploaded label the seller downloads through the API. */
    public function getHasLabelFileAttribute(): bool
    {
        return $this->label_path !== null;
    }

    /** Tracking can be corrected until delivered/returned/lost, up to MAX_EDITS times. */
    public function getCanEditAttribute(): bool
    {
        return in_array($this->status, ['shipped', 'in_transit'], true)
            && $this->edit_count < self::MAX_EDITS
            && $this->label_source === 'own';
    }
}
