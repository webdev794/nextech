<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Scheduled admin emails (CRM). Needs one cron: * * * * * php artisan schedule:run
Schedule::command('emails:send-scheduled')->everyMinute()->withoutOverlapping();

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
