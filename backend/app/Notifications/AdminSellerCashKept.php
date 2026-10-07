<?php

namespace App\Notifications;

use App\Models\Order;
use App\Models\Shop;
use App\Support\Money;
use App\Support\SellerCod;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** To admins: a seller collected (and kept) the cash for a cash-on-delivery order. */
class AdminSellerCashKept extends Notification
{
    use Queueable;

    public function __construct(public Order $order, public Shop $shop) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $currency = $this->order->currency ?: 'usd';
        $owed = SellerCod::owedCents($this->shop);
        $mail = (new MailMessage)
            ->subject("{$this->shop->name} kept ".Money::format($this->order->total_cents, $currency)." cash — order #{$this->order->id}")
            ->line("{$this->shop->name} delivered order #{$this->order->id} and kept the ".Money::format($this->order->total_cents, $currency).' cash. '.\App\Support\Branding::name().'\'s commission and fees were taken from their balance.');
        if ($owed > 0) {
            $mail->line("They now owe ".\App\Support\Branding::name()." ".Money::format($owed, $currency).($owed > SellerCod::maxOwedCents($this->shop->market) ? ' — over the limit, so cash on delivery is paused for them.' : '.'));
        }

        return $mail->action('Open Sellers', rtrim((string) config('app.url'), '/').'/#/admin');
    }
}
