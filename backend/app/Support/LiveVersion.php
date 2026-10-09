<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * A "something changed" marker for live pages: any saved or deleted record
 * (except constant background updates like sign-in tokens and rider GPS
 * pings) rewrites public/live.json once per request. Open admin / seller /
 * rider pages read that small static file every few seconds — the web server
 * serves it like an image, with no PHP or database work — and fetch their
 * data again only when it changed. So a change on one page shows on the
 * others within seconds, without reloading them and without loading the server.
 */
final class LiveVersion
{
    /** User fields that change all the time without anything to show. */
    private const QUIET_USER_FIELDS = ['rider_last_lat', 'rider_last_lng', 'rider_last_located_at', 'rider_last_seen_at', 'updated_at', 'remember_token', 'last_seen_at'];

    private static bool $pending = false;

    public static function path(): string
    {
        return public_path('live.json');
    }

    public static function current(): string
    {
        $data = @file_get_contents(self::path());

        return (string) (json_decode((string) $data, true)['v'] ?? '0');
    }

    public static function touched(mixed $model): void
    {
        if (! $model instanceof Model || $model instanceof \Laravel\Sanctum\PersonalAccessToken || app()->runningUnitTests()) {
            return;
        }
        if ($model instanceof User && $model->exists && ! $model->wasRecentlyCreated && array_diff(array_keys($model->getChanges()), self::QUIET_USER_FIELDS) === []) {
            return;
        }
        // Written once, at the end of the request (or command), however many records changed.
        if (! self::$pending) {
            self::$pending = true;
            app()->terminating(function () {
                self::$pending = false;
                self::write();
            });
        }
    }

    public static function write(): void
    {
        try {
            file_put_contents(self::path(), json_encode(['v' => sprintf('%.4f', microtime(true))]), LOCK_EX);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
