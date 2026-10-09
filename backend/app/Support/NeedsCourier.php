<?php

namespace App\Support;

use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\AdminNotice;
use Illuminate\Support\Facades\Notification;

/**
 * Orders admin has to send by courier by hand: an own-rider order no rider
 * took within the time limit, or an order outside every store's area when
 * there's no courier connection to book it. Admin is emailed and sees it in
 * the 🔔 and the Orders "Needs courier" filter until it goes out.
 */
final class NeedsCourier
{

    /** A real courier account is connected (otherwise bookings would only be test ones). */
    public static function hasCourierConnection(): bool
    {
        return config('courier.provider', 'mock') === 'real';
    }

    /** No rider online when it became ready: tell admin once, early — the deadline still runs for riders who log in. */
    public static function headsUp(Order $order): void
    {
        if ($order->needs_courier_at || ! \Illuminate\Support\Facades\Cache::add("rider-headsup-{$order->id}", 1, now()->addDay())) {
            return;
        }
        $by = RiderAssignment::deadline($order)->format('j M H:i');
        try {
            Notification::send(User::where('is_admin', true)->get(), new AdminNotice(
                "No rider online for order #{$order->id}",
                "Order #{$order->id} is ready but no rider at its store is online (on leave, logged out or on a break). Riders who come online can still take it until {$by}; after that you'll be told to deliver it yourself or book a courier. You can call a rider now.",
            ));
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public static function flag(Order $order, string $reason): void
    {
        if ($order->needs_courier_at) {
            return;
        }
        $order->forceFill(['needs_courier_at' => now(), 'needs_courier_reason' => $reason])->save();
        $why = match ($reason) {
            'outside_area' => 'it’s outside every store’s delivery area and no courier account is connected',
            'no_rider_free' => 'no rider is available at its store (on leave, logged out or paused)',
            default => 'no rider took it in time',
        };
        try {
            Notification::send(User::where('is_admin', true)->get(), new AdminNotice(
                "Order #{$order->id} needs a courier",
                "Order #{$order->id} is ready but {$why}. Call a rider to log in and pick it up, deliver it yourself, or book a courier and enter it on the order (Orders → open the order → Shipped with a courier).",
            ));
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /** Every minute: own-rider orders ready longer than the riders' time with nobody who took it (outside-area ones are flagged at once). */
    public static function sweep(): int
    {
        $cutoff = now()->subSeconds(RiderAssignment::offerSeconds());
        $orders = Order::query()->where('status', 'ready_for_delivery')->where('delivery_method', 'own_rider')
            ->whereNull('needs_courier_at')->whereNull('rider_accepted_at')
            ->where(fn ($q) => $q->where('ready_at', '<=', $cutoff)->orWhere(fn ($q) => $q->whereNull('ready_at')->where('updated_at', '<=', $cutoff)))
            ->get();
        foreach ($orders as $order) {
            self::flag($order, 'no_rider');
        }

        return $orders->count();
    }
}
