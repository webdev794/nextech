<?php

namespace App\Notifications;

use App\Models\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** To admins when a seller suggests a new category for a product. */
class AdminCategorySuggested extends Notification
{
    use Queueable;

    public function __construct(public Product $product) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("New category suggested: “{$this->product->suggested_category_name}”")
            ->line("{$this->product->shop?->name} suggested a new category “{$this->product->suggested_category_name}” for “{$this->product->name}”.")
            ->line('Add it under Admin → Categories if it fits, then set the product’s category.')
            ->action('Open Admin', rtrim((string) config('app.url'), '/').'/#/admin');
    }
}
