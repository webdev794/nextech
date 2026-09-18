<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SendOtpCode extends Notification
{
    use Queueable;

    public function __construct(public string $code, public int $ttlMinutes)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('Your NexTech verification code')
            ->greeting('Verification code')
            ->line("Enter this code to continue: **{$this->code}**")
            ->line("The code expires in {$this->ttlMinutes} minutes.")
            ->line('If you did not request this, you can ignore this email.');
    }
}
