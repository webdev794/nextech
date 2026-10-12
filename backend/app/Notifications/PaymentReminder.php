<?php

namespace App\Notifications;

use App\Models\Order;
use App\Support\Branding;
use App\Support\Money;
use App\Support\SiteUrl;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** To a buyer who placed an order but didn't finish paying for it. */
class PaymentReminder extends Notification
{
    use Queueable;

    public function __construct(public Order $order) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Complete your payment for order #{$this->order->id}")
            ->line("Your order #{$this->order->id} (".Money::format((int) $this->order->total_cents, $this->order->currency ?? 'usd').') is waiting for payment — it isn’t packed or sent until it’s paid.')
            ->action('Complete payment', SiteUrl::to('#/account/orders'))
            ->line('Changed your mind? You can cancel it there too.')
            ->salutation('— '.Branding::name());
    }
}
