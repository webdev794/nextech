<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsSeller
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->seller !== null, 403, 'Seller access required.');

        return $next($request);
    }
}
