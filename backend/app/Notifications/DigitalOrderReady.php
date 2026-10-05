<?php

namespace App\Notifications;

use App\Models\Order;
use App\Support\Branding;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** To the buyer when the digital items they paid for are ready to download. */
class DigitalOrderReady extends Notification
{
    use Queueable;

    public function __construct(public Order $order) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $brand = Branding::current();
        $brandName = ($brand['store_name'] ?? '') !== '' ? $brand['store_name'] : config('app.name');
        $items = $this->order->items()->where('fulfilled_by', 'digital')->get();

        $mail = (new MailMessage)
            ->subject("Your download is ready — order #{$this->order->id}")
            ->greeting('Ready to download!')
            ->line('Thanks for your order. These are ready in Your downloads:');
        foreach ($items as $item) {
            $mail->line('• '.$item->product_name);
        }

        return $mail->action('Go to Your downloads', rtrim((string) config('app.url'), '/').'/#/account/downloads')
            ->line('License keys (if any) are shown next to each download. Download links are private to your account.')
            ->salutation("— {$brandName}");
    }
}
