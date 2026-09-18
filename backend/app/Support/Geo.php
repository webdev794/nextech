<?php

namespace App\Support;

use App\Models\Store;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class Geo
{
    private const NOMINATIM = 'https://nominatim.openstreetmap.org';

    /**
     * Great-circle distance between two lat/lng points, in kilometres.
     */
    public static function haversineKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthKm = 6371.0088;

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return $earthKm * 2 * asin(min(1.0, sqrt($a)));
    }

    /**
     * The store nearest a point and its great-circle distance in km. Expects
     * stores that already have latitude/longitude.
     *
     * @param  iterable<Store>  $stores
     * @return array{store: Store, km: float}|null
     */
    public static function nearestStore(iterable $stores, float $lat, float $lng): ?array
    {
        $best = null;
        foreach ($stores as $store) {
            if ($store->latitude === null || $store->longitude === null) {
                continue;
            }
            $km = self::haversineKm((float) $store->latitude, (float) $store->longitude, $lat, $lng);
            if ($best === null || $km < $best['km']) {
                $best = ['store' => $store, 'km' => $km];
            }
        }

        return $best;
    }

    /**
     * Stores whose delivery radius covers a point, nearest first. A customer is
     * deliverable if this is non-empty; the first entry is the store that serves
     * them (and whose per-product availability applies).
     *
     * @param  iterable<Store>  $stores
     * @return list<array{store: Store, km: float}>
     */
    public static function coveringStores(iterable $stores, float $lat, float $lng): array
    {
        $hits = [];
        foreach ($stores as $store) {
            if ($store->latitude === null || $store->longitude === null) {
                continue;
            }
            $km = self::haversineKm((float) $store->latitude, (float) $store->longitude, $lat, $lng);
            if ($km <= (float) $store->delivery_radius_km) {
                $hits[] = ['store' => $store, 'km' => $km];
            }
        }

        usort($hits, fn ($a, $b) => $a['km'] <=> $b['km']);

        return $hits;
    }

    /**
     * The nearest store whose radius covers the point, or null when none do.
     *
     * @param  iterable<Store>  $stores
     * @return array{store: Store, km: float}|null
     */
    public static function servingStore(iterable $stores, float $lat, float $lng): ?array
    {
        return self::coveringStores($stores, $lat, $lng)[0] ?? null;
    }

    /**
     * Forward geocode via OpenStreetMap Nominatim. Returns address candidates,
     * best match first. When a centre is given, results are restricted to a box
     * around it (and anything still improbably far is dropped) so a sparse
     * street query lands near the store rather than on a namesake elsewhere.
     * Cached for a day.
     *
     * @return list<array{label: string, full: string, line1: string, city: string, state: string, postal_code: string, lat: float, lon: float}>
     */
    public static function search(string $query, ?float $lat = null, ?float $lng = null, float $maxKm = 40.0): array
    {
        $query = self::tidy($query);
        if (mb_strlen($query) < 2) {
            return [];
        }

        $key = 'geo:search:'.md5("{$query}|{$lat}|{$lng}|{$maxKm}");
        $cached = Cache::get($key);
        if (is_array($cached)) {
            return $cached;
        }

        $forms = self::relaxations($query);
        $hasCentre = $lat !== null && $lng !== null;

        // A query with a postcode or several address lines is unambiguous: honour
        // it even if it's far from the store (the storefront then shows the
        // out-of-area notice). A vague query ("First Floor") stays fenced to the
        // store region so it can't match a namesake on another continent.
        $specific = self::isSpecific($query);

        if ($hasCentre && ! $specific) {
            $results = self::firstHit($forms, $lat, $lng, true);
            if ($results === []) {
                $results = self::firstHit($forms, $lat, $lng, false);
            }
            $results = array_values(array_filter(
                $results,
                fn ($r) => self::haversineKm($lat, $lng, $r['lat'], $r['lon']) <= $maxKm,
            ));
        } else {
            $results = self::firstHit($forms, $lat, $lng, false);
        }

        // Only cache a real answer, so a transient failure or an empty pass
        // isn't frozen in for a day.
        if ($results !== []) {
            Cache::put($key, $results, now()->addDay());
        }

        return $results;
    }

    /**
     * @param  list<string>  $forms
     * @return list<array{label: string, full: string, line1: string, city: string, state: string, postal_code: string, lat: float, lon: float}>
     */
    private static function firstHit(array $forms, ?float $lat, ?float $lng, bool $bounded): array
    {
        foreach ($forms as $form) {
            $hits = self::rawSearch($form, $lat, $lng, $bounded);
            if ($hits !== []) {
                return array_values(array_map(self::mapPlace(...), $hits));
            }
        }

        return [];
    }

    /**
     * Reverse geocode a single point to the app's address shape. Cached for a day.
     *
     * @return array{label: string, full: string, line1: string, city: string, state: string, postal_code: string, lat: float, lon: float}|null
     */
    public static function reverse(float $lat, float $lng): ?array
    {
        $key = 'geo:reverse:'.md5("{$lat},{$lng}");
        $cached = Cache::get($key);
        if (is_array($cached)) {
            return $cached;
        }

        try {
            $place = self::http()->get(self::NOMINATIM.'/reverse', [
                'format' => 'jsonv2',
                'addressdetails' => 1,
                'lat' => $lat,
                'lon' => $lng,
            ])->json();

            if (is_array($place) && isset($place['lat'], $place['lon'])) {
                $mapped = self::mapPlace($place);
                Cache::put($key, $mapped, now()->addDay());

                return $mapped;
            }
        } catch (\Throwable) {
            // fall through
        }

        return null;
    }

    /**
     * Best-effort forward geocode to a single [lat, lng] pair. Used by store and
     * checkout address resolution.
     *
     * @return array{0: float|null, 1: float|null}
     */
    public static function geocode(string $query): array
    {
        $hit = self::search($query)[0] ?? null;

        return $hit ? [$hit['lat'], $hit['lon']] : [null, null];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function rawSearch(string $query, ?float $lat, ?float $lng, bool $bounded = false): array
    {
        if (mb_strlen(trim($query)) < 2) {
            return [];
        }

        try {
            $params = [
                'format' => 'jsonv2',
                'addressdetails' => 1,
                'limit' => 6,
                'q' => $query,
            ];

            if ($lat !== null && $lng !== null) {
                $d = 0.6; // ~65 km half-box
                $params['viewbox'] = implode(',', [$lng - $d, $lat + $d, $lng + $d, $lat - $d]);
                if ($bounded) {
                    $params['bounded'] = 1;
                }
            }

            $body = self::http()->get(self::NOMINATIM.'/search', $params)->json();

            return is_array($body) ? array_values(array_filter($body, 'is_array')) : [];
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @param  array<string, mixed>  $place
     * @return array{label: string, full: string, line1: string, city: string, state: string, postal_code: string, lat: float, lon: float}
     */
    private static function mapPlace(array $place): array
    {
        $a = $place['address'] ?? [];
        $city = $a['city'] ?? $a['town'] ?? $a['village'] ?? $a['suburb'] ?? $a['county'] ?? $a['state_district'] ?? '';
        $iso = (string) ($a['ISO3166-2-lvl4'] ?? '');
        $state = (string) ($a['state'] ?? ($iso && str_contains($iso, '-') ? explode('-', $iso)[1] : ''));
        $full = (string) ($place['display_name'] ?? '');
        $parts = array_values(array_filter(array_map('trim', explode(',', $full))));
        $postcode = (string) ($a['postcode'] ?? '');

        // Prefer the structured house/road; otherwise take the leading part of the
        // display name (up to the city, if we know it) so line1 is never blank.
        $line1 = trim(implode(' ', array_filter([$a['house_number'] ?? null, $a['road'] ?? null])));
        if ($line1 === '' && $parts !== []) {
            $cut = $city !== '' ? array_search($city, $parts, true) : false;
            $line1 = implode(', ', array_slice($parts, 0, is_int($cut) && $cut > 0 ? $cut : min(3, count($parts))));
        }

        return [
            'label' => implode(', ', array_slice($parts, 0, 2)) ?: 'Selected location',
            'full' => $full,
            'line1' => $line1,
            'city' => (string) $city,
            'state' => $state,
            'postal_code' => $postcode,
            'lat' => (float) $place['lat'],
            'lon' => (float) $place['lon'],
        ];
    }

    private static function http(): PendingRequest
    {
        $request = Http::withHeaders([
            'Accept-Language' => 'en',
            'User-Agent' => (string) config('services.nominatim.user_agent'),
        ])->timeout(6);

        // Windows/XAMPP PHP frequently has no CA bundle configured; fall back to
        // the one committed with the app so HTTPS verification succeeds.
        $bundle = resource_path('certs/cacert.pem');
        if (! ini_get('openssl.cafile') && ! ini_get('curl.cainfo') && is_file($bundle)) {
            $request->withOptions(['verify' => $bundle]);
        }

        return $request;
    }

    private static function tidy(string $query): string
    {
        return trim((string) preg_replace('/\s*,\s*/', ', ', (string) preg_replace('/\s+/', ' ', $query)));
    }

    /** A postcode or 4+ comma-separated parts means the user typed a real address. */
    private static function isSpecific(string $query): bool
    {
        $parts = count(array_filter(array_map('trim', explode(',', $query)), fn ($p) => $p !== ''));

        return $parts >= 4 || preg_match('/\b\d{4,6}\b/', $query) === 1;
    }

    /**
     * Progressively looser forms of a comma-separated address, tried in order.
     * OSM has sparse street data in much of the world, so the relaxed forms
     * drop the front (unit / building / shop number) while keeping the tail
     * (locality, city, postcode) so the map still lands in the right place. A
     * trailing bare abbreviation ("..., CH", read as Switzerland) is removed.
     *
     * @return list<string>
     */
    private static function relaxations(string $query): array
    {
        $parts = array_values(array_filter(array_map('trim', explode(',', $query)), fn ($p) => $p !== ''));
        if ($parts === []) {
            return [];
        }

        $forms = [implode(', ', $parts)];

        if (count($parts) > 1 && preg_match('/^[A-Za-z]{2}$/', (string) end($parts))) {
            array_pop($parts);
        }

        // Unit designators aren't geographic — drop leading ones ("First Floor",
        // "SCO 489-490", "Flat 3", "Shop 12", "Plot 7", "Block A").
        $unit = '/^(flat|floor|first floor|ground floor|second floor|third floor|room|unit|shop|plot|block|sco|scf|booth|kiosk|house|door|no\.?)\b/i';
        while (count($parts) > 2 && preg_match($unit, $parts[0])) {
            array_shift($parts);
            $forms[] = implode(', ', $parts);
        }

        // Then drop from the front one segment at a time, always keeping the
        // last two (locality + city / pincode).
        for ($start = 1; count($parts) - $start >= 2; $start++) {
            $forms[] = implode(', ', array_slice($parts, $start));
        }

        // Final fallback: the most place-like trailing segment on its own.
        $forms[] = (string) end($parts);

        return array_values(array_filter(array_unique($forms), fn ($f) => mb_strlen($f) >= 3));
    }
}
