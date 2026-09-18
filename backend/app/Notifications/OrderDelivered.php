<?php

namespace App\Notifications;

use App\Models\Order;
use App\Support\Branding;
use App\Support\OrderReceipt;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the customer once their order is both paid and delivered: a short
 * summary in the body, with the full itemised bill attached as a PDF.
 */
class OrderDelivered extends Notification
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
        $money = fn ($cents) => '$'.number_format(((int) $cents) / 100, 2);

        $brand = Branding::current();
        $brandName = ($brand['store_name'] ?? '') !== '' ? $brand['store_name'] : config('app.name');
        $when = $order->delivered_at?->format('j M Y, g:i a');

        $mail = (new MailMessage)
            ->subject("Your {$brandName} order #{$order->id} was delivered")
            ->greeting('Thanks for your order!')
            ->line($when
                ? "Order #{$order->id} was delivered on {$when}."
                : "Order #{$order->id} has been delivered.")
            ->line('**Order summary**');

        foreach ($order->items as $item) {
            $label = $item->product_name.($item->variant_label ? " ({$item->variant_label})" : '');
            $mail->line("{$item->quantity} × {$label} — ".$money($item->line_total_cents));
        }

        $mail->line('**Total paid: '.$money($order->total_cents).'**')
            ->line('Your full itemised bill is attached as a PDF.')
            ->salutation("— {$brandName}");

        $mail->attachData(
            OrderReceipt::pdf($order)->output(),
            OrderReceipt::filename($order),
            ['mime' => 'application/pdf'],
        );

        return $mail;
    }
}
