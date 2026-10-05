<?php

namespace App\Support;

use App\Models\SalesTaxRate;
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * US sales tax by where the order goes. Mode "state" (default): the buyer's
 * ZIP code rate from the live lookup when an API key is set (cached per ZIP),
 * else the state's rate from the table (config/sales_tax.php + admin edits).
 * Mode "flat": the one rate in checkout fees, as before. Markets with
 * tax-inclusive prices (India GST) never add tax on top.
 */
class SalesTax
{
    public static function mode(): string
    {
        return Setting::get('sales_tax_mode', config('sales_tax.default_mode', 'state')) === 'flat' ? 'flat' : 'state';
    }

    /**
     * Every state's rate: the built-in table with admin edits on top. Null =
     * left blank by the admin, so the default rate (Settings → Charges) applies.
     *
     * @return array<string,int|null>
     */
    public static function stateRates(): array
    {
        $edits = Setting::get('sales_tax_states', []);
        $rates = (array) config('sales_tax.states', []);
        foreach (is_array($edits) ? $edits : [] as $code => $bps) {
            if (array_key_exists($code, $rates)) {
                $rates[$code] = $bps === null || $bps === '' ? null : max(0, min(10000, (int) $bps));
            }
        }

        return $rates;
    }

    /**
     * "Fetch automatically": ask the lookup for each state's own rate (from a
     * ZIP in its capital) and save them. Returns what was updated / failed.
     *
     * @return array{updated: list<string>, failed: list<string>}
     */
    public static function fetchStateRates(): array
    {
        $key = self::apiKey();
        abort_if($key === null, 422, 'Add a sales tax lookup API key in Secure access first.');
        $zips = (array) config('sales_tax.sample_zips', []);
        $timeout = (int) config('sales_tax.lookup.timeout_seconds', 4) + 4;
        $responses = Http::pool(fn ($pool) => collect($zips)->map(fn ($zip, $code) => $pool->as($code)->withHeaders(['X-Api-Key' => $key])->timeout($timeout)->get((string) config('sales_tax.lookup.url'), ['zip_code' => $zip]))->values()->all());

        $rates = self::stateRates();
        $updated = [];
        $failed = [];
        foreach ($zips as $code => $zip) {
            $response = $responses[$code] ?? null;
            $row = $response instanceof \Illuminate\Http\Client\Response && $response->successful()
                ? collect((array) $response->json())->first(fn ($r) => is_array($r) && isset($r['state_rate']))
                : null;
            $rate = is_array($row) ? (float) $row['state_rate'] : null;
            if ($rate === null || $rate < 0 || $rate > 0.25) {
                $failed[] = $code;

                continue;
            }
            $rates[$code] = (int) round($rate * 10000);
            $updated[] = $code;
        }
        if ($updated) {
            Setting::put('sales_tax_states', $rates);
        }

        return ['updated' => $updated, 'failed' => $failed];
    }

    public static function apiKey(): ?string
    {
        $key = trim((string) (Setting::get('sales_tax_api_key') ?? ''));

        return $key === '' ? null : $key;
    }

    /**
     * The rate (basis points) to add on top for an order shipped to this
     * address; $flatBps (the default rate) when the mode is flat, the state
     * isn't known, or its rate was left blank.
     */
    public static function rateBps(?string $market, ?string $state, ?string $postalCode, int $flatBps): int
    {
        if (Market::taxInclusive($market)) {
            return 0;
        }
        if (strtoupper((string) $market) !== 'US' || self::mode() === 'flat') {
            return $flatBps;
        }

        $zip = substr(preg_replace('/\D/', '', (string) $postalCode), 0, 5);
        if (strlen($zip) === 5 && ($rate = self::zipRate($zip)) !== null) {
            return $rate;
        }

        $code = SellerShipping::stateCode($state, 'US');

        // Unknown state, or left blank in the table: the default rate.
        return $code !== null ? (self::stateRates()[$code] ?? $flatBps) : $flatBps;
    }

    /** A ZIP's combined rate: cached, else fetched; null without an API key or when the lookup fails. */
    public static function zipRate(string $zip): ?int
    {
        $key = self::apiKey();
        if ($key === null) {
            return null;
        }

        $cached = SalesTaxRate::find($zip);
        if ($cached && $cached->fetched_at?->gt(now()->subDays((int) config('sales_tax.lookup.cache_days', 30)))) {
            return $cached->rate_bps;
        }
        // A recent failure for this ZIP: don't retry on every cart change.
        if (Cache::has("sales_tax_miss:{$zip}")) {
            return $cached?->rate_bps;
        }

        $rate = self::fetch($zip, $key);
        if ($rate === null) {
            Cache::put("sales_tax_miss:{$zip}", true, now()->addHour());

            return $cached?->rate_bps; // an older rate beats none
        }
        SalesTaxRate::updateOrCreate(['zip_code' => $zip], ['rate_bps' => $rate, 'source' => 'api-ninjas', 'fetched_at' => now()]);

        return $rate;
    }

    /** API Ninjas sales-tax endpoint: [{ "zip_code": "90210", "total_rate": "0.102500", ... }]. */
    private static function fetch(string $zip, string $key): ?int
    {
        try {
            $response = Http::withHeaders(['X-Api-Key' => $key])
                ->timeout((int) config('sales_tax.lookup.timeout_seconds', 4))
                ->get((string) config('sales_tax.lookup.url'), ['zip_code' => $zip]);
            if (! $response->successful()) {
                Log::warning('Sales tax lookup failed', ['zip' => $zip, 'status' => $response->status()]);

                return null;
            }
            $row = collect((array) $response->json())->first(fn ($r) => is_array($r) && isset($r['total_rate']));
            $total = is_array($row) ? (float) $row['total_rate'] : null;
            if ($total === null || $total < 0 || $total > 0.25) {
                return null;
            }

            return (int) round($total * 10000);
        } catch (Throwable $e) {
            Log::warning('Sales tax lookup error', ['zip' => $zip, 'error' => $e->getMessage()]);

            return null;
        }
    }

    /** Admin settings payload (no secrets). */
    public static function adminPayload(): array
    {
        $key = self::apiKey();
        $names = Market::states('US');
        $defaults = (array) config('sales_tax.states', []);

        return [
            'mode' => self::mode(),
            'states' => collect(self::stateRates())->map(fn ($bps, $code) => ['code' => $code, 'name' => $names[$code] ?? $code, 'rate_bps' => $bps, 'builtin_bps' => (int) ($defaults[$code] ?? 0)])->values()->all(),
            'api_key_set' => $key !== null,
            'api_key_hint' => $key !== null ? '…'.substr($key, -4) : null,
            'cached_zips' => SalesTaxRate::count(),
        ];
    }
}
