<?php

namespace App\Support;

use App\Models\OrderPackage;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * A seller's own (local) delivery at the buyer's door — by one of the seller's riders
 * or the seller: send the delivery code again, mark it delivered without the code
 * (with the reason), or the buyer refused to pay (cash on delivery).
 */
final class PackageDelivery
{
    private static function assertAtDoor(OrderPackage $package): void
    {
        abort_unless($package->carrier === SellerShipping::LOCAL, 422, 'Only your own deliveries have a delivery code.');
        abort_unless($package->status === 'out_for_delivery', 422, 'This delivery isn’t out for delivery.');
    }

    /** Email the buyer their delivery code again (once a minute at most). Returns where it went (masked). */
    public static function sendCode(OrderPackage $package): string
    {
        self::assertAtDoor($package);
        abort_unless($package->delivery_code, 422, 'This delivery has no code.');
        abort_unless(Cache::add("package-code-{$package->id}", 1, now()->addMinute()), 429, 'Just sent — wait a minute before sending it again.');
        $order = $package->order;
        $order->emailCustomer(new \App\Notifications\DeliveryCodeAgain($order, $package->delivery_code, $package->shop?->name));
        $email = (string) $order->user?->email;
        [$name, $domain] = array_pad(explode('@', $email, 2), 2, '');

        return $email === '' ? 'the buyer' : mb_substr($name, 0, 1).'***'.(mb_strlen($name) > 1 ? mb_substr($name, -1) : '').'@'.$domain;
    }

    /** Delivered without the buyer's code: the reason is kept, and the seller is told when a rider did it. */
    public static function deliveredWithoutCode(OrderPackage $package, User $by, string $note, bool $cash): void
    {
        self::assertAtDoor($package);
        $note = trim($note);
        abort_if(mb_strlen($note) < 5, 422, 'Say why there’s no code (at least a few words).');
        $byRider = $package->rider_id === $by->id;
        $cod = $package->order?->payment_method === 'cod';
        abort_if($cod && ! $cash, 422, 'Cash on delivery: collect the cash and tick "Cash collected".');
        SellerProgress::advance($package, 'delivered', $byRider ? 'rider' : 'seller', $cod);
        $package->forceFill(['delivered_by' => $byRider ? 'rider' : 'self', 'delivery_verified' => false, 'delivery_note' => $note])->save();
        RiderMoney::creditSellerDelivery($package->fresh('shop'));
        if ($byRider && $cod) {
            SellerRiderCash::collected($package->fresh('shop.seller'), $by);
        }
        if ($byRider && ($seller = $package->shop?->seller)) {
            SellerNotify::send($seller, $seller->user, "Order #{$package->order_id} delivered without the code",
                "{$by->name} marked order #{$package->order_id} delivered without the buyer’s delivery code. Reason: {$note}");
        }
    }

    /** Cash on delivery and the buyer refused to pay: the items go back to the seller. */
    public static function refused(OrderPackage $package, User $by, string $note): void
    {
        self::assertAtDoor($package);
        $order = $package->order;
        abort_unless($order?->payment_method === 'cod' && $order->payment_status !== 'paid', 422, 'This order isn’t waiting for cash on delivery.');
        $note = trim($note);
        abort_if(mb_strlen($note) < 5, 422, 'Say what happened (at least a few words).');
        $byRider = $package->rider_id === $by->id;
        $history = (array) $package->status_history;
        $history[] = ['status' => 'refused', 'by' => $byRider ? 'rider' : 'seller', 'at' => now()->toIso8601String(), 'note' => $note];
        $package->forceFill(['status' => 'returned', 'status_history' => $history, 'progress_updated_at' => now(), 'delivery_note' => "Refused by the buyer: {$note}"])->save();
        // Nothing on the order reached the buyer: the order is cancelled.
        $packages = $order->packages()->get();
        if (! $order->items()->where('fulfilled_by', '!=', 'seller')->exists() && $packages->every(fn ($p) => $p->status === 'returned')) {
            $order->update(['status' => 'cancelled', 'payment_status' => 'cancelled', 'cancelled_by' => $byRider ? 'rider' : 'seller', 'cancel_reason' => "Refused by the buyer: {$note}"]);
            $order->restoreGiftCardRedemptions();
        }
        if ($byRider && ($seller = $package->shop?->seller)) {
            SellerNotify::send($seller, $seller->user, "Buyer refused order #{$package->order_id}",
                "The buyer refused to pay for order #{$package->order_id}: {$note}. {$by->name} will bring the items back to you — check them when you get them back.");
        }
    }
}
