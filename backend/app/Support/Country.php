<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Country configuration: the config/countries.php catalog, filtered down to
 * the admin-chosen "active countries" list (settings key "active_countries",
 * same Setting::get/put convention as CheckoutFees). Single- vs multi-country
 * behavior falls out purely from how many codes are active — no mode flag.
 */
class Country
{
    /**
     * Every country in the catalog, keyed by ISO code, each entry carrying
     * its own 'code'.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function all(): array
    {
        return collect(config('countries', []))
            ->map(fn (array $entry, string $code) => ['code' => $code] + $entry)
            ->all();
    }

    /**
     * The admin-configured active countries, in the order the setting lists
     * them. Defensive against a stale code left over after the catalog
     * changes. Falls back to US (today's implicit default) when nothing
     * valid is configured.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function active(): array
    {
        $all = self::all();
        $codes = Setting::get('active_countries', ['US']);
        $codes = is_array($codes) ? $codes : ['US'];

        $active = collect($codes)
            ->map(fn ($code) => is_string($code) ? strtoupper($code) : null)
            ->filter(fn (?string $code) => $code !== null && isset($all[$code]))
            ->unique()
            ->map(fn (string $code) => $all[$code])
            ->values()
            ->all();

        return $active !== [] ? $active : array_slice(array_values($all), 0, 1);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function find(string $code): ?array
    {
        return self::all()[strtoupper($code)] ?? null;
    }

    /**
     * @return array<int, array{code: string, name: string}>
     */
    public static function forSelect(): array
    {
        return collect(self::active())
            ->map(fn (array $c) => ['code' => $c['code'], 'name' => $c['name']])
            ->all();
    }
}
