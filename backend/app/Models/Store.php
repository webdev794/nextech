<?php

namespace App\Models;

use App\Support\Market;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Store extends Model
{
    protected static function booted(): void
    {
        // Every store belongs to a country store; one saved without (seed data,
        // imports) joins the home country rather than silently never delivering.
        static::creating(function (Store $store): void {
            $store->country = $store->country ?: Market::home();
        });
    }

    protected $fillable = [
        'name', 'line1', 'line2', 'city', 'state', 'postal_code',
        'latitude', 'longitude', 'delivery_radius_km', 'is_active', 'local_delivery_active',
    ];

    /** The seller's shop this store belongs to (their local-delivery base), or null for NexTech's own store. */
    public function shop(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    /** NexTech's own stores only — checkout areas, NexTech deliveries, stock and rider pay never use sellers' stores. */
    public function scopeOwn(\Illuminate\Database\Eloquent\Builder $query): void
    {
        $query->whereNull('stores.shop_id');
    }

    public function isSellers(): bool
    {
        return $this->shop_id !== null;
    }

    /** Riders who serve this store (used by auto-assignment). */
    public function riders(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'rider_store')->withPivot(['linked_at', 'cash_paused_at', 'cash_later_at']);
    }

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'delivery_radius_km' => 'integer',
            'is_active' => 'boolean',
            'rider_hours' => 'array',
            'local_delivery_active' => 'boolean',
            'hiring_open' => 'boolean',
        ];
    }

    public function hasCoordinates(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }
}
