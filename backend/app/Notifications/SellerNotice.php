<?php

namespace App\Notifications;

use App\Support\Branding;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** An admin decision on something a seller submitted (approved, rejected, paid…), by email. */
class SellerNotice extends Notification
{
    use Queueable;

    public function __construct(public string $subject, public string $body, public bool $routine = false) {}

    public function via(object $notifiable): array
    {
        // Routine reminders can be turned off (they're in the app anyway); important notices always go.
        return $this->routine && isset($notifiable->email_routine) && ! $notifiable->email_routine ? [] : ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $brand = Branding::current();
        $brandName = ($brand['store_name'] ?? '') !== '' ? $brand['store_name'] : config('app.name');

        return (new MailMessage)
            ->subject($this->subject)
            ->line($this->body)
            ->action('Open Seller Center', rtrim((string) config('app.url'), '/').'/#/seller')
            ->line('Questions? Reply in Messages in Seller Center.')
            ->salutation("— {$brandName}");
    }
}
