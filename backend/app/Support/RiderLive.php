<?php

namespace App\Support;

use App\Models\User;

/**
 * Where the rider is, for the buyer — only while their order is out for delivery,
 * and only from a live location (Rider app) in the last 15 minutes.
 */
final class RiderLive
{
    /** The rider's first name and phone, for the buyer (only while out for delivery). */
    public static function contact(?int $riderId): ?array
    {
        $rider = $riderId ? User::find($riderId) : null;

        return $rider ? ['name' => strtok((string) $rider->name, ' '), 'phone' => $rider->phone] : null;
    }

    /** @return array{km: ?float, minutes_ago: int, map: string}|null */
    public static function forBuyer(?int $riderId, ?array $address): ?array
    {
        $rider = $riderId ? User::find($riderId) : null;
        $at = $rider?->riderLocation();
        if (! $at || ($at['source'] ?? null) !== 'live' || ! $rider->rider_last_located_at) {
            return null;
        }
        $point = isset($address['latitude'], $address['longitude']) ? [(float) $address['latitude'], (float) $address['longitude']] : null;

        return [
            'km' => $point ? round(Geo::haversineKm($at['lat'], $at['lng'], $point[0], $point[1]), 1) : null,
            'minutes_ago' => (int) $rider->rider_last_located_at->diffInMinutes(now()),
            'map' => 'https://www.google.com/maps/search/?api=1&query='.$at['lat'].','.$at['lng'],
        ];
    }
}
