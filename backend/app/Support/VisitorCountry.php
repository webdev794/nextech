<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * The visitor's country from their IP, so a first visit opens the right
 * country store (storefront, Seller Center application, rider sign-up).
 * A CDN country header wins when present (Cloudflare etc.); otherwise the IP
 * is looked up once a day per address (ipwho.is, then ipapi.co). Local and
 * private addresses, and any lookup failure, give null — callers fall back.
 */
class VisitorCountry
{
    private const HEADERS = ['CF-IPCountry', 'CloudFront-Viewer-Country', 'X-Vercel-IP-Country', 'X-Country-Code'];

    public static function detect(Request $request): ?string
    {
        foreach (self::HEADERS as $header) {
            $code = strtoupper(trim((string) $request->header($header)));
            if (preg_match('/^[A-Z]{2}$/', $code) && ! in_array($code, ['XX', 'T1'], true)) {
                return $code;
            }
        }

        $ip = (string) $request->ip();
        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return null;
        }

        $code = Cache::remember('visitor-country:'.hash('sha256', $ip), now()->addDay(), fn () => self::lookup($ip) ?? '');

        return $code !== '' ? $code : null;
    }

    /** The country store to open for this visitor: their country when it's one we sell in. */
    public static function market(Request $request): ?string
    {
        $country = self::detect($request);

        return $country && in_array($country, Market::codes(), true) ? $country : null;
    }

    private static function lookup(string $ip): ?string
    {
        try {
            $code = strtoupper((string) Http::timeout(3)->get("https://ipwho.is/{$ip}", ['fields' => 'success,country_code'])->json('country_code'));
            if (preg_match('/^[A-Z]{2}$/', $code)) {
                return $code;
            }
        } catch (\Throwable) {
            // try the next service
        }
        try {
            $code = strtoupper(trim(Http::timeout(3)->get("https://ipapi.co/{$ip}/country/")->body()));
            if (preg_match('/^[A-Z]{2}$/', $code)) {
                return $code;
            }
        } catch (\Throwable) {
            // give up — the caller falls back
        }

        return null;
    }
}
