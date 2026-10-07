<?php

namespace App\Notifications;

use App\Models\Order;
use App\Support\Branding;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Sent when an order is placed and confirmed (paid by card, cash on delivery, or covered by store credit). */
class OrderConfirmed extends Notification
{
    use Queueable;

    public function __construct(public Order $order) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $order = $this->order->loadMissing('items');
        $brand = Branding::current();
        $brandName = ($brand['store_name'] ?? '') !== '' ? $brand['store_name'] : config('app.name');
        $money = fn ($cents) => Money::format((int) $cents, $order->currency);
        $address = (array) $order->delivery_address;

        $mail = (new MailMessage)
            ->subject("Order #{$order->id} confirmed — {$brandName}")
            ->greeting('Thanks for your order!')
            ->line("We've received order #{$order->id}".($order->payment_method === 'cod' ? ' — you\'ll pay cash on delivery.' : '.'))
            ->line('**Order summary**');

        foreach ($order->items as $item) {
            $label = $item->product_name.($item->variant_label ? " ({$item->variant_label})" : '');
            $mail->line("{$item->quantity} × {$label} — ".$money($item->line_total_cents));
        }

        $mail->line('**Total: '.$money($order->total_cents).'**');

        $to = implode(', ', array_filter([$address['line1'] ?? null, $address['city'] ?? null, $address['state'] ?? null, $address['postal_code'] ?? null]));
        if ($to !== '') {
            $mail->line("Delivering to: {$to}");
        }

        return $mail->line('We\'ll email you again when it\'s on its way. You can track it any time under Your orders.')
            ->salutation("— {$brandName}");
    }
}
