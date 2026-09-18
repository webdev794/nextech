<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Store extends Model
{
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
