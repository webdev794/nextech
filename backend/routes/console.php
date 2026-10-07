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

// Sellers who ship themselves: a daily email listing orders waiting on their
// update (packed, picked up, in transit, out for delivery, cash collected).
Artisan::command('sellers:remind-updates', function () {
    $sent = 0;
    App\Models\Shop::query()->where('is_active', true)->whereIn('fulfillment_mode', ['self', 'label'])->with('seller.user')->each(function ($shop) use (&$sent) {
        $orders = App\Support\SellerProgress::needsUpdate($shop);
        $user = $shop->seller?->user;
        if ($orders->isNotEmpty() && $user?->email) {
            try {
                $user->notify(new App\Notifications\SellerUpdateReminder($orders));
                $sent++;
            } catch (Throwable $e) {
                report($e);
            }
        }
    });
    $this->info("Reminders sent: {$sent}");
})->purpose('Email sellers about orders waiting on their status update');
Schedule::command('sellers:remind-updates')->dailyAt('09:00')->withoutOverlapping();
