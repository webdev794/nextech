<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DeliveryHandoverCode extends Notification
{
    use Queueable;

    public function __construct(public int $orderId, public string $code, public int $ttlMinutes)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject("Your delivery code for order #{$this->orderId}")
            ->greeting('Your rider is here')
            ->line("Give this code to the delivery rider to confirm you received order #{$this->orderId}:")
            ->line("**{$this->code}**")
            ->line("It expires in {$this->ttlMinutes} minutes.");
    }
}
