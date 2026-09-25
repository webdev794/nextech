<?php

namespace App\Notifications;

use App\Models\Order;
use App\Models\OrderPackage;
use App\Support\Branding;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent when an order is on its way: a NexTech rider/courier is out for
 * delivery, or a seller shipped a package (with carrier + tracking link).
 */
class OrderShipped extends Notification
{
    use Queueable;

    public function __construct(public Order $order, public ?OrderPackage $package = null) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $order = $this->order->loadMissing('items', 'shipment');
        $brand = Branding::current();
        $brandName = ($brand['store_name'] ?? '') !== '' ? $brand['store_name'] : config('app.name');

        $mail = (new MailMessage)
            ->subject("Order #{$order->id} is on its way — {$brandName}")
            ->greeting('Good news!');

        if ($this->package) {
            $package = $this->package->loadMissing('items', 'shop');
            $names = $order->items->keyBy('id');
            $mail->line(($package->shop?->name ? "{$package->shop->name} shipped" : 'A package from your order #'.$order->id.' has shipped').($package->shop?->name ? " part of order #{$order->id}." : '.'));
            foreach ($package->items as $row) {
                $item = $names->get($row->order_item_id);
                if ($item) {
                    $mail->line("{$row->quantity} × {$item->product_name}".($item->variant_label ? " ({$item->variant_label})" : ''));
                }
            }
            $mail->line("Carrier: {$package->carrier} · Tracking number: **{$package->tracking_number}**");
            if ($package->tracking_url) {
                $mail->action('Track your package', $package->tracking_url);
            }
        } else {
            $mail->line("Order #{$order->id} is out for delivery.");
            if ($order->shipment?->tracking_number) {
                $mail->line("Carrier: {$order->shipment->carrier} · Tracking number: **{$order->shipment->tracking_number}**");
            } else {
                $mail->line('Your rider will read out a delivery code when they arrive — we\'ll email it to you then.');
            }
        }

        return $mail->line('You can follow it any time under Your orders.')
            ->salutation("— {$brandName}");
    }
}
