<?php

namespace App\Notifications;

use App\Models\Product;
use App\Support\Branding;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** To a seller when admin approved a product that still lacks some details: it's live, please add them soon. */
class SellerProductFollowup extends Notification
{
    use Queueable;

    /** @param  list<string>  $missing */
    public function __construct(public Product $product, public array $missing) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $brand = Branding::current();
        $brandName = ($brand['store_name'] ?? '') !== '' ? $brand['store_name'] : config('app.name');

        $mail = (new MailMessage)
            ->subject("“{$this->product->name}” is live — please add the missing details")
            ->greeting('Your product is live!')
            ->line("We approved “{$this->product->name}” so you can start selling now. A few details are still missing — please add them soon:");
        foreach ($this->missing as $item) {
            $mail->line('• '.$item);
        }

        return $mail->action('Edit the product', rtrim((string) config('app.url'), '/').'/#/seller')
            ->line('Products with details still missing may be paused if they aren’t completed.')
            ->salutation("— {$brandName}");
    }
}
