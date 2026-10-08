<?php

namespace App\Notifications;

use App\Support\Branding;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** A short notice to the store's admins by email (e.g. a seller removed one of their riders). */
class AdminNotice extends Notification
{
    use Queueable;

    public function __construct(public string $subject, public string $body) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject($this->subject)->line($this->body)
            ->action('Open the admin', rtrim((string) config('app.url'), '/').'/#/admin')
            ->salutation('— '.Branding::name());
    }
}
