<?php

namespace App\Support;

use App\Models\Setting;
use App\Models\Shop;
use Illuminate\Support\Str;

/**
 * Holidays admin adds on top of each country's built-in list:
 *  - for a whole country (shop_id null): sellers see it in Holiday settings and tick it if they work;
 *  - for one store (shop_id set): that store's day off — always off, counted in delivery dates,
 *    the buyer's note and its riders' timetable.
 * Sellers ask for store days off; admin adds them (store or country) or declines.
 */
final class ExtraHolidays
{
    /** @return array<int, array{id: string, market: string, date: string, name: string, shop_id: ?int}> */
    public static function all(): array
    {
        return array_values((array) Setting::get('extra_holidays', []));
    }

    /** Country-wide extras as date => key (key "x" + date), for a year in a market. */
    public static function countryDates(int $year, ?string $market): array
    {
        $out = [];
        foreach (self::all() as $h) {
            if ($h['shop_id'] === null && strtoupper($h['market']) === strtoupper((string) $market) && str_starts_with($h['date'], $year.'-')) {
                $out[$h['date']] = 'x'.$h['date'];
            }
        }

        return $out;
    }

    /** Names of a market's country-wide extras: key => name. */
    public static function countryNames(?string $market): array
    {
        $out = [];
        foreach (self::all() as $h) {
            if ($h['shop_id'] === null && strtoupper($h['market']) === strtoupper((string) $market)) {
                $out['x'.$h['date']] = $h['name'];
            }
        }

        return $out;
    }

    /** One store's own days off: date => name. */
    public static function storeDays(Shop $shop): array
    {
        $out = [];
        foreach (self::all() as $h) {
            if ((int) $h['shop_id'] === (int) $shop->id) {
                $out[$h['date']] = $h['name'];
            }
        }

        return $out;
    }

    public static function add(string $market, string $date, string $name, ?int $shopId = null): void
    {
        $list = self::all();
        $list = array_values(array_filter($list, fn ($h) => ! ($h['date'] === $date && strtoupper($h['market']) === strtoupper($market) && $h['shop_id'] === $shopId)));
        $list[] = ['id' => (string) Str::uuid(), 'market' => strtoupper($market), 'date' => $date, 'name' => $name, 'shop_id' => $shopId];
        usort($list, fn ($a, $b) => strcmp($a['date'], $b['date']));
        Setting::put('extra_holidays', $list);
    }

    public static function remove(string $id): void
    {
        Setting::put('extra_holidays', array_values(array_filter(self::all(), fn ($h) => $h['id'] !== $id)));
    }

    // ------------------------------------------------------------- requests

    /** @return array<int, array{id: string, shop_id: int, date: string, name: string, reason: ?string, at: string}> */
    public static function requests(): array
    {
        return array_values((array) Setting::get('holiday_requests', []));
    }

    public static function request(Shop $shop, string $date, string $name, ?string $reason): void
    {
        $list = self::requests();
        $list[] = ['id' => (string) Str::uuid(), 'shop_id' => $shop->id, 'date' => $date, 'name' => $name, 'reason' => $reason, 'at' => now()->toIso8601String()];
        Setting::put('holiday_requests', $list);
    }

    public static function takeRequest(string $id): ?array
    {
        $list = self::requests();
        $found = collect($list)->firstWhere('id', $id);
        Setting::put('holiday_requests', array_values(array_filter($list, fn ($r) => $r['id'] !== $id)));

        return $found;
    }
}
