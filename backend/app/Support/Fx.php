<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Currency conversion for cross-border orders (a buyer in one country buying
 * from a seller in another). Rates are the market mid-rate per 1 USD, fetched
 * once a day from two free sources (Frankfurter / ECB and open.er-api.com) and
 * cross-checked; admin can pin a fixed rate instead. Buyers pay the converted
 * price plus the platform margin; the seller is credited their own listed
 * price (the ledger converts back at the same rate, so the margin stays with
 * the platform).
 */
class Fx
{
    /** Used only until the first successful fetch. */
    private const FALLBACK = ['usd' => 1.0, 'inr' => 95.0];

    public const DEFAULT_MARGIN_BPS = 300;

    /** @return array{margin_bps: int, manual: array<string, float|null>} */
    public static function settings(): array
    {
        $saved = (array) Setting::get('fx', []);

        return [
            'margin_bps' => (int) ($saved['margin_bps'] ?? self::DEFAULT_MARGIN_BPS),
            'manual' => array_map(fn ($v) => $v !== null && $v !== '' && (float) $v > 0 ? (float) $v : null, (array) ($saved['manual'] ?? [])),
        ];
    }

    /** Units of $currency per 1 USD (mid-rate, or admin's fixed rate). */
    public static function mid(string $currency): float
    {
        $currency = strtolower($currency);
        if ($currency === 'usd') {
            return 1.0;
        }
        $manual = self::settings()['manual'][$currency] ?? null;
        if ($manual) {
            return $manual;
        }
        $auto = self::auto();

        return (float) ($auto['rates'][$currency] ?? self::FALLBACK[$currency] ?? 1.0);
    }

    /**
     * What one unit of $from costs in $to, for a buyer: mid-rate plus the
     * platform margin. 1 when the currencies match.
     */
    public static function multiplier(string $from, string $to): float
    {
        if (strtolower($from) === strtolower($to)) {
            return 1.0;
        }

        return self::mid($to) / self::mid($from) * (1 + self::settings()['margin_bps'] / 10000);
    }

    public static function convert(int $cents, string $from, string $to, ?float $multiplier = null): int
    {
        return (int) round($cents * ($multiplier ?? self::multiplier($from, $to)));
    }

    /** The auto-fetched rates, refreshed when a day old. @return array{rates: array<string, float>, fetched_at: ?string, sources: list<string>} */
    public static function auto(): array
    {
        $auto = (array) Setting::get('fx_auto', []);
        $fetched = isset($auto['fetched_at']) ? strtotime((string) $auto['fetched_at']) : 0;
        if ($fetched < time() - 86400) {
            // One refresh at a time; a failed one is retried an hour later.
            if (Cache::add('fx-refreshing', 1, 3600)) {
                $auto = self::refresh() ?? $auto;
            }
        }

        return ['rates' => (array) ($auto['rates'] ?? []), 'fetched_at' => $auto['fetched_at'] ?? null, 'sources' => (array) ($auto['sources'] ?? [])];
    }

    /** Fetch today's rates. A source's figure is used only when both agree (within 2%), or it's the only one and within 5% of yesterday's. */
    public static function refresh(): ?array
    {
        $currencies = collect(Market::codes())->map(fn ($c) => strtolower(Market::currency($c)))->reject(fn ($c) => $c === 'usd')->unique()->values();
        if ($currencies->isEmpty()) {
            return null;
        }
        $upper = $currencies->map(fn ($c) => strtoupper($c))->implode(',');
        $sources = [];
        try {
            $sources['frankfurter'] = (array) Http::timeout(5)->get('https://api.frankfurter.dev/v1/latest', ['base' => 'USD', 'symbols' => $upper])->json('rates');
        } catch (\Throwable) {
            // next source
        }
        try {
            $sources['open.er-api'] = (array) Http::timeout(5)->get('https://open.er-api.com/v6/latest/USD')->json('rates');
        } catch (\Throwable) {
            // next source
        }

        $previous = (array) (Setting::get('fx_auto', [])['rates'] ?? []);
        $rates = $previous;
        $used = [];
        foreach ($currencies as $currency) {
            $values = collect($sources)->map(fn ($r) => (float) ($r[strtoupper($currency)] ?? 0))->filter(fn ($v) => $v > 0);
            if ($values->isEmpty()) {
                continue;
            }
            $prev = (float) ($previous[$currency] ?? 0);
            $agree = $values->count() >= 2 && $values->max() / $values->min() <= 1.02;
            $rate = $agree ? $values->avg() : (float) $values->first();
            if (! $agree && $prev > 0 && abs($rate / $prev - 1) > 0.05) {
                continue; // a single source jumping >5% overnight — keep yesterday's
            }
            $rates[$currency] = round($rate, 4);
            $used = array_merge($used, $values->keys()->all());
        }
        if (! $used) {
            return null;
        }

        $auto = ['rates' => $rates, 'fetched_at' => now()->toIso8601String(), 'sources' => array_values(array_unique($used))];
        Setting::put('fx_auto', $auto);

        return $auto;
    }

    /** For admin settings. */
    public static function status(): array
    {
        $settings = self::settings();
        $auto = self::auto();
        $currencies = collect(Market::codes())->map(fn ($c) => strtolower(Market::currency($c)))->reject(fn ($c) => $c === 'usd')->unique()->values();

        return [
            'margin_bps' => $settings['margin_bps'],
            'fetched_at' => $auto['fetched_at'],
            'sources' => $auto['sources'],
            'currencies' => $currencies->map(fn ($c) => [
                'currency' => $c,
                'market_rate' => $auto['rates'][$c] ?? null,
                'manual_rate' => $settings['manual'][$c] ?? null,
                'in_use' => self::mid($c),
                'buyer_rate' => round(self::mid($c) * (1 + $settings['margin_bps'] / 10000), 4),
            ])->all(),
        ];
    }
}
