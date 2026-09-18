<?php

namespace App\Support;

use App\Models\Store;
use Illuminate\Support\Collection;

/**
 * Resolves which store serves a customer location. One place so the catalog,
 * checkout and the delivery-ETA endpoint all agree on "the store nearest you
 * whose delivery radius reaches you".
 */
class StoreLocator
{
    /**
     * Active stores that have coordinates.
     *
     * @return Collection<int, Store>
     */
    public static function locatedStores(): Collection
    {
        return Store::query()
            ->where('is_active', true)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get();
    }

    /**
     * The store that serves this point — nearest one whose radius covers it.
     * Null when no store reaches the point (or none are configured / located).
     */
    public static function servingStore(?float $lat, ?float $lng): ?Store
    {
        if ($lat === null || $lng === null) {
            return null;
        }

        $stores = self::locatedStores();
        if ($stores->isEmpty()) {
            return null;
        }

        return Geo::servingStore($stores, $lat, $lng)['store'] ?? null;
    }
}
