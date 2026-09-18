<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsRider
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless($user?->is_rider, 403, 'Delivery rider access required.');

        // Presence heartbeat — the rider app polls /rider/orders every 15s, so
        // this doubles as "last online". Throttled to at most one write/minute.
        // Best-effort: a heartbeat write must never 500 the request (e.g. right
        // after a deploy, before `php artisan migrate` has run).
        if (! $user->rider_last_seen_at || $user->rider_last_seen_at->lt(now()->subSeconds(60))) {
            try {
                $user->forceFill(['rider_last_seen_at' => now()])->saveQuietly();
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return $next($request);
    }
}
