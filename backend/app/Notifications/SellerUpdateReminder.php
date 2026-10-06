<?php

namespace App\Notifications;

use App\Support\Branding;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

/** Daily, to a seller with orders waiting on their update (SellerProgress::needsUpdate). */
class SellerUpdateReminder extends Notification
{
    use Queueable;

    /** @param  Collection<int, array{order_id: int, reason: string}>  $orders */
    public function __construct(public Collection $orders) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $brand = Branding::current();
        $brandName = ($brand['store_name'] ?? '') !== '' ? $brand['store_name'] : config('app.name');
        $count = $this->orders->count();

        $mail = (new MailMessage)
            ->subject("{$count} ".($count === 1 ? 'order needs' : 'orders need')." your update — {$brandName} Seller Center")
            ->greeting('Please update your orders')
            ->line('Buyers and '.\App\Support\Branding::name().' follow each order through the steps you update — packed, picked up by the courier, in transit, out for delivery, delivered (and cash collected for cash on delivery). These are waiting on you:');
        foreach ($this->orders->take(20) as $row) {
            $mail->line("Order #{$row['order_id']} — {$row['reason']}");
        }
        if ($count > 20) {
            $mail->line('…and '.($count - 20).' more.');
        }

        return $mail->action('Open Ship orders', rtrim((string) config('app.url'), '/').'/#/seller')
            ->line('Orders left without updates may be flagged to '.\App\Support\Branding::name().' support.')
            ->salutation("— {$brandName}");
    }
}
