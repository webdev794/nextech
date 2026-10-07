<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** To admins: seller orders still not shipped well after their ship-by date (SellerProgress::chase). */
class AdminSellerOverdue extends Notification
{
    use Queueable;

    /** @param  list<array{order_id: int, shop: string, ship_by: string, reminders: int}>  $orders */
    public function __construct(public array $orders) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $count = count($this->orders);
        $mail = (new MailMessage)
            ->subject("{$count} seller ".($count === 1 ? 'order is' : 'orders are').' overdue — not shipped yet')
            ->line('These orders are past their ship-by date and the seller hasn\'t shipped them, despite reminders. Contact the seller or the buyer, or cancel and refund:');
        foreach (array_slice($this->orders, 0, 30) as $o) {
            $mail->line("Order #{$o['order_id']} — {$o['shop']} · ship by {$o['ship_by']} · {$o['reminders']} reminder(s) sent");
        }

        return $mail->action('Open Orders', rtrim((string) config('app.url'), '/').'/#/admin');
    }
}
