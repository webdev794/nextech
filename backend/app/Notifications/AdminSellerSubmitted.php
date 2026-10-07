<?php

namespace App\Notifications;

use App\Models\Seller;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** To admins: a seller submitted tax, compliance or bank details for review. */
class AdminSellerSubmitted extends Notification
{
    use Queueable;

    public function __construct(public Seller $seller, public string $what) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $name = $this->seller->shop?->name ?? $this->seller->company_name;

        return (new MailMessage)
            ->subject("{$name} submitted their {$this->what} for review")
            ->line("{$name} submitted their {$this->what}. Review it in Admin → Sellers (it's also under Sellers in the top bar).")
            ->action('Open Sellers', rtrim((string) config('app.url'), '/').'/#/admin');
    }
}
