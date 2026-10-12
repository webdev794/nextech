<?php

namespace App\Notifications;

use App\Models\Order;
use App\Support\Branding;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** To the buyer: their delivery code again (asked for by the rider or seller at the door). */
class DeliveryCodeAgain extends Notification
{
    use Queueable;

    public function __construct(public Order $order, public string $code, public ?string $shop = null) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Your delivery code for order #{$this->order->id}")
            ->line("Your order #{$this->order->id}".($this->shop ? " from {$this->shop}" : '').' is at your door.')
            ->line("Your delivery code is **{$this->code}** — read it out to confirm you received it.")
            ->salutation('— '.Branding::name());
    }
}
