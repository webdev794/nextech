<?php

namespace App\Notifications;

use App\Models\Order;
use App\Support\Branding;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

/**
 * To a seller when an order with their items is confirmed: what was ordered
 * and — when they ship it themselves — the date to ship by. Buyer contact
 * details stay out (Seller Center shows only what's needed to ship).
 */
class SellerNewOrder extends Notification
{
    use Queueable;

    /** @param  Collection<int, \App\Models\OrderItem>  $items  this shop's lines on the order */
    public function __construct(public Order $order, public Collection $items, public bool $shipsItself, public ?string $shipBy = null) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $brand = Branding::current();
        $brandName = ($brand['store_name'] ?? '') !== '' ? $brand['store_name'] : config('app.name');
        $money = fn ($cents) => Money::format((int) $cents, $this->order->currency);

        $mail = (new MailMessage)
            ->subject("New order #{$this->order->id}".($this->shipsItself && $this->shipBy ? " — ship by {$this->shipBy}" : '')." — {$brandName} Seller Center")
            ->greeting('You have a new order!')
            ->line("Order #{$this->order->id} includes:");

        foreach ($this->items as $item) {
            $label = $item->product_name.($item->variant_label ? " ({$item->variant_label})" : '');
            $mail->line("{$item->quantity} × {$label} — ".$money($item->line_total_cents));
        }

        $digital = $this->items->every(fn ($i) => $i->fulfilled_by === 'digital');
        $mail->line($digital
            ? 'Digital download — the buyer gets it automatically once paid. Nothing to ship.'
            : ($this->shipsItself
            ? 'You ship this order. Pack it, add the courier and tracking number in Seller Center → Manage orders'.($this->shipBy ? " by {$this->shipBy}" : '').', then update its status as it moves.'
            : \App\Support\Branding::name().' collects and delivers this order — have it packed and ready for pickup.'));

        return $mail->action('Open Manage orders', rtrim((string) config('app.url'), '/').'/#/seller')
            ->salutation("— {$brandName}");
    }
}
