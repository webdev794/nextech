<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Scheduled admin emails (CRM). Needs one cron: * * * * * php artisan schedule:run
Schedule::command('emails:send-scheduled')->everyMinute()->withoutOverlapping();

// Online-courier orders: pull tracking every 30 minutes and complete the ones the
// courier reports delivered (the admin "Sync tracking" button does the same by hand).
Artisan::command('orders:sync-courier-tracking', function () {
    $done = 0;
    \App\Models\Order::query()->where('delivery_method', 'online_courier')
        ->whereNotIn('status', ['completed', 'cancelled'])->has('shipment')->with('shipment')
        ->each(function ($order) use (&$done) {
            try {
                $done += \App\Support\CourierTracking::sync($order) ? 1 : 0;
            } catch (\Throwable $e) {
                report($e);
            }
        });
    $this->info("Delivered: {$done}");
})->purpose('Complete online-courier orders the courier reports delivered');
Schedule::command('orders:sync-courier-tracking')->everyThirtyMinutes()->withoutOverlapping();

// Sellers' hosted download links (Drive, Dropbox, own server): re-check daily —
// a file can be unshared or deleted after the seller added it.
Artisan::command('digital:check-links', function () {
    $broken = 0;
    \App\Models\ProductFile::query()->whereNotNull('external_url')->each(function ($file) use (&$broken) {
        $broken += \App\Support\DigitalProducts::recheckLink($file)['status'] === 'broken' ? 1 : 0;
    });
    $this->info("Broken links: {$broken}");
})->purpose('Re-check sellers\' hosted download links');
Schedule::command('digital:check-links')->dailyAt('03:30')->withoutOverlapping();

// Sellers who ship themselves: hourly, email orders waiting on their update
// (packed, shipped, out for delivery, delivered, cash collected) — each order at
// most once per admin's repeat interval — and alert admin about overdue ones.
Artisan::command('sellers:remind-updates', function () {
    $result = App\Support\SellerProgress::chase();
    $this->info("Sellers reminded: {$result['reminded']} · overdue sent to admin: {$result['escalated']}");
})->purpose('Remind sellers about orders waiting on their update; alert admin about overdue ones');
Schedule::command('sellers:remind-updates')->hourly()->withoutOverlapping();

// Lightning deals set to auto-restart: begin the next round (new % from the range) when one ends or sells out.
Artisan::command('deals:restart-lightning', function () {
    $this->info('Restarted: '.App\Support\LightningDeals::restartDue());
})->purpose('Start the next round of auto-restarting lightning deals');
Schedule::command('deals:restart-lightning')->everyTenMinutes()->withoutOverlapping();
