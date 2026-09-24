<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Http\Request;

/**
 * Selling markets (config/markets.php): one per country. A seller sells in
 * their registration country's market; NexTech's own stock is in the home
 * market. Shoppers pick a market (the X-Market header / "market" param) and
 * only ever see — and check out — that market's products, in its currency.
 */
class Market
{
    /** NexTech's own market (its stores, riders and own inventory). */
    public static function home(): string
    {
        $home = strtoupper((string) Setting::get('home_market', 'US'));

        return self::known($home) ? $home : 'US';
    }

    /** US keeps the original (un-suffixed) settings keys for fees, payouts and rider pay. */
    public static function usesLegacySettings(?string $code): bool
    {
        return strtoupper((string) $code) === 'US';
    }

    public static function known(?string $code): bool
    {
        return $code !== null && is_array(config('markets.'.strtoupper($code)));
    }

    /**
     * Markets shoppers can pick: the admin's active countries that have a
     * market profile, always including home.
     *
     * @return list<string>
     */
    public static function codes(): array
    {
        $codes = collect(Country::active())->pluck('code')
            ->filter(fn ($code) => self::known($code))
            ->prepend(self::home())
            ->unique()->values()->all();

        return $codes;
    }

    /** A valid, open market for this code — or home. */
    public static function resolve(?string $code): string
    {
        $code = strtoupper(trim((string) $code));

        return in_array($code, self::codes(), true) ? $code : self::home();
    }

    public static function fromRequest(Request $request): string
    {
        return self::resolve($request->header('X-Market') ?: $request->input('market'));
    }

    /** The market a seller registered in (their country), falling back to home. */
    public static function forCountry(?string $country): string
    {
        $country = strtoupper((string) $country);

        return self::known($country) ? $country : self::home();
    }

    /** @return array<string, mixed> */
    public static function profile(?string $code): array
    {
        $code = self::known($code) ? strtoupper($code) : self::home();

        return ['code' => $code] + (array) config('markets.'.$code);
    }

    public static function currency(?string $code): string
    {
        return (string) (self::profile($code)['currency'] ?? 'usd');
    }

    public static function taxInclusive(?string $code): bool
    {
        return (self::profile($code)['tax']['mode'] ?? 'exclusive') === 'inclusive';
    }

    /** @return array<string, string> */
    public static function states(?string $code): array
    {
        return (array) (self::profile($code)['states'] ?? []);
    }

    /** @return array<string, string> */
    public static function holidays(?string $code): array
    {
        return (array) (self::profile($code)['holidays'] ?? []);
    }

    /** @return array<string, array{0: string, 1: ?string, 2: ?string}> */
    public static function carriers(?string $code): array
    {
        return (array) (self::profile($code)['carriers'] ?? []);
    }

    /** Every carrier across markets (for tracking links on existing packages). */
    public static function allCarriers(): array
    {
        return collect(config('markets', []))->reduce(fn (array $all, array $m) => $all + (array) ($m['carriers'] ?? []), []);
    }

    /** The GST slice inside a GST-inclusive amount. */
    public static function includedTaxCents(int $grossCents, int $rateBps): int
    {
        return $rateBps > 0 ? (int) round($grossCents * $rateBps / (10000 + $rateBps)) : 0;
    }

    /**
     * Validation for the product label details a GST-inclusive market needs:
     * HSN code and GST rate for the tax invoice, and country of origin +
     * manufacturer/packer/importer (Legal Metrology (Packaged Commodities)
     * Rules 2011, rule 6; Consumer Protection (E-Commerce) Rules 2020, rule
     * 6(5)). Required from sellers there when $required; optional otherwise.
     *
     * @return array<string, list<mixed>>
     */
    public static function productRules(?string $market, bool $required): array
    {
        $need = $required && self::taxInclusive($market) ? 'required' : 'sometimes';
        $rates = (array) (self::profile($market)['gst_rates_bps'] ?? [0, 300, 500, 1800, 4000]);

        return [
            'hsn_code' => [$need, 'nullable', 'string', 'regex:/^\d{4}(\d{2})?(\d{2})?$/'],
            'gst_rate_bps' => [$need, 'nullable', 'integer', 'in:'.implode(',', $rates)],
            'country_of_origin' => [$need, 'nullable', 'string', 'max:60'],
            'manufacturer_info' => [$need, 'nullable', 'string', 'max:500'],
        ];
    }

    /** What the storefronts need to render a market. */
    public static function publicProfile(string $code): array
    {
        $p = self::profile($code);
        $country = Country::find($p['code']);

        return [
            'code' => $p['code'],
            'name' => $country['name'] ?? $p['code'],
            'currency' => $p['currency'],
            'currency_symbol' => $country['currency_symbol'] ?? null,
            'locale' => $p['locale'],
            'tax_mode' => $p['tax']['mode'],
            'tax_label' => $p['tax']['label'],
            'postal_label' => $country['address']['postal_label'] ?? 'Postal code',
            'postal_regex' => $country['address']['postal_regex'] ?? null,
            'phone_code' => $country['phone_code'] ?? null,
            'states' => self::states($code),
            'home' => $p['code'] === self::home(),
        ];
    }

    /** @return list<array<string, mixed>> */
    public static function publicList(): array
    {
        return array_map(fn ($code) => self::publicProfile($code), self::codes());
    }
}
