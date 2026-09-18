<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RiderMessage extends Notification
{
    use Queueable;

    public function __construct(public int $orderId, public string $body)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject("Message from your delivery rider — order #{$this->orderId}")
            ->line('Your delivery rider sent you a message:')
            ->line("\"{$this->body}\"")
            ->line('Open the app to reply.');
    }
}
