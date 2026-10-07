<?php

namespace App\Notifications;

use App\Models\Shop;
use App\Support\Branding;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** To admins: a seller sent products — or an edit to a live product — for review. */
class AdminProductsSubmitted extends Notification
{
    use Queueable;

    /** @param  list<string>  $names */
    public function __construct(public Shop $shop, public array $names, public bool $edit = false) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $count = count($this->names);
        $what = $this->edit ? 'an edit to “'.($this->names[0] ?? 'a product').'” (it stays live until you approve the change)'
            : ($count === 1 ? '“'.$this->names[0].'”' : $count.' products');
        $mail = (new MailMessage)
            ->subject($this->shop->name.' sent '.($this->edit ? 'a product edit' : ($count === 1 ? 'a product' : $count.' products')).' for review')
            ->line($this->shop->name.' sent '.$what.' for review.');
        if (! $this->edit && $count > 1) {
            foreach (array_slice($this->names, 0, 10) as $name) {
                $mail->line('• '.$name);
            }
        }

        return $mail->action('Review in Admin → Products', rtrim((string) config('app.url'), '/').'/#/admin')
            ->line('They\'re under Products → Waiting for review (and Sellers in the top bar). Products are sold in the seller\'s country — pick All countries in the top bar to see every one.')
            ->salutation('— '.Branding::name());
    }
}
