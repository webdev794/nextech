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
        'latitude', 'longitude', 'delivery_radius_km', 'is_active',
    ];

    /** Riders who serve this store (used by auto-assignment). */
    public function riders(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'rider_store');
    }

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'delivery_radius_km' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function hasCoordinates(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }
}
