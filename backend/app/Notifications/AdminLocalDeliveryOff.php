<?php

namespace App\Notifications;

use App\Models\Store;
use App\Support\Branding;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** To admins: a seller asked to turn their local delivery off (riders are linked, so admin decides). */
class AdminLocalDeliveryOff extends Notification
{
    use Queueable;

    public function __construct(public Store $store, public int $riders) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $shop = $this->store->shop?->name ?? 'A seller';

        return (new MailMessage)
            ->subject("{$shop} wants to turn off local delivery")
            ->line("{$shop} asked to turn off their own local delivery. {$this->riders} rider".($this->riders === 1 ? ' is' : 's are').' linked to their store.')
            ->line('Riders aren’t told anything until you approve. Approve or keep it on under Stores / hubs (or the Sellers button in the top bar).')
            ->action('Open the admin', rtrim((string) config('app.url'), '/').'/#/admin')
            ->salutation('— '.Branding::name());
    }
}
