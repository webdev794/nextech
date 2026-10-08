<?php

namespace App\Notifications;

use App\Support\Branding;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** A notice to a rider by email (e.g. a store they deliver for ended local delivery). */
class RiderNotice extends Notification
{
    use Queueable;

    public function __construct(public string $subject, public string $body) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject($this->subject)->line($this->body)->salutation('— '.Branding::name());
    }
}
