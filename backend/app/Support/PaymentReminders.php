<?php

namespace App\Support;

use App\Models\Order;
use App\Notifications\PaymentReminder;
use Illuminate\Support\Facades\Cache;

/** Unpaid card orders: the buyer is reminded about 1 hour and again 24 hours after ordering. */
final class PaymentReminders
{
    public static function send(): int
    {
        $n = 0;
        $orders = Order::query()->where('status', 'pending_payment')->where('payment_method', '!=', 'cod')
            ->where('created_at', '<=', now()->subHour())->where('created_at', '>=', now()->subDays(3))->get();
        foreach ($orders as $order) {
            $stage = $order->created_at->lte(now()->subDay()) ? 'day' : 'hour';
            if (Cache::add("pay-remind-{$order->id}-{$stage}", 1, now()->addDays(4))) {
                try {
                    $order->emailCustomer(new PaymentReminder($order));
                    $n++;
                } catch (\Throwable $e) {
                    report($e);
                }
            }
        }

        return $n;
    }
}
