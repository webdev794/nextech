<?php

use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\EnsureUserIsRider;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(function (Schedule $schedule): void {
        // Backstop for the lazy sweep (which runs on every rider/admin order
        // poll): re-offers a delivery whose 60s window lapsed even when nobody
        // is polling. Needs an OS cron running `php artisan schedule:run`.
        $schedule->command('riders:sweep-offers')->everyMinute()->withoutOverlapping();
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(
            fn (Request $request) => $request->is('api/*') ? null : route('login')
        );
        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
            'rider' => EnsureUserIsRider::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
