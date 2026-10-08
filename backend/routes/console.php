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

// Riders whose notice period ends today: remind the admins to settle their final pay.
Artisan::command('riders:notice-due', function () {
    $due = \App\Models\User::query()->where('is_rider', true)->whereNull('rider_notice_processed_at')->whereDate('rider_leaving_on', today())->get();
    foreach ($due as $rider) {
        \Illuminate\Support\Facades\Notification::send(\App\Models\User::where('is_admin', true)->get(), new \App\Notifications\AdminNotice(
            "{$rider->name}'s notice period ends today", "{$rider->name}'s last working day is today. Settle their final pay (check open orders and any cash they hold), then mark the notice processed under Riders."));
    }
    $this->info('Due: '.$due->count());
})->purpose('Remind admins when a rider\'s notice period ends');
Schedule::command('riders:notice-due')->dailyAt('08:00')->withoutOverlapping();

// Sellers' riders and cash on delivery: remind at the end of the day, pause the next morning.
Artisan::command('riders:seller-cash {when=evening}', function (string $when) {
    $n = $when === 'morning' ? \App\Support\SellerRiderCash::morning() : \App\Support\SellerRiderCash::endOfDay();
    $this->info(($when === 'morning' ? 'Paused: ' : 'Reminded: ').$n);
})->purpose('Remind about / pause for sellers\' cash riders still hold');
Schedule::command('riders:seller-cash evening')->dailyAt('20:00')->withoutOverlapping();
Schedule::command('riders:seller-cash morning')->dailyAt('06:00')->withoutOverlapping();

// Month end: cash riders still hold for sellers comes out of their earnings and goes to the sellers.
Artisan::command('riders:offset-seller-cash', function () {
    $moved = 0;
    foreach (\App\Models\User::query()->where('is_rider', true)->get() as $rider) {
        $moved += \App\Support\RiderMoney::offsetSellerCash($rider);
    }
    $this->info("Moved: {$moved}");
})->purpose('Give sellers the cash riders kept, from the riders\' earnings');
Schedule::command('riders:offset-seller-cash')->monthlyOn(1, '05:00')->withoutOverlapping();
