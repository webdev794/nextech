<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * The admin "Secure access" unlock: after re-entering their password (or an
 * emailed code) an admin gets a token, sent back in the X-Secure-Access header.
 * The admin console locks it again on leaving the section or after a minute idle.
 */
class SecureAccess
{
    public const HEADER = 'X-Secure-Access';

    private static function key(Request $request): string
    {
        $login = $request->user()->currentAccessToken()?->id ?? 'session';

        return "secure_access_grant:{$request->user()->id}:{$login}";
    }

    public static function grant(Request $request, string $token, int $minutes): void
    {
        Cache::put(self::key($request), hash('sha256', $token), now()->addMinutes($minutes));
    }

    public static function check(Request $request): bool
    {
        $token = (string) $request->header(self::HEADER, '');
        $stored = Cache::get(self::key($request));

        return $stored !== null && $token !== '' && hash_equals($stored, hash('sha256', $token));
    }

    /** Lock again now (leaving Secure access, or idle). */
    public static function revoke(Request $request): void
    {
        if (self::check($request)) {
            Cache::forget(self::key($request));
        }
    }

    public static function assert(Request $request): void
    {
        abort_unless(self::check($request), 403, 'Unlock the Secure access section first.');
    }
}
