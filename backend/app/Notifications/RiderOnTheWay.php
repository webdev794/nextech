<?php

namespace App\Notifications;

use App\Models\Order;
use App\Models\User;
use App\Support\Branding;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** To the buyer: who is delivering their order now, and how to call them. */
class RiderOnTheWay extends Notification
{
    use Queueable;

    public function __construct(public Order $order, public User $rider, public ?string $shop = null, public ?string $code = null) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $name = strtok((string) $this->rider->name, ' ');
        $mail = (new MailMessage)
            ->subject("{$name} is delivering order #{$this->order->id}")
            ->line("{$name} is on the way with your order #{$this->order->id}".($this->shop ? " from {$this->shop}" : '').'.');
        if ($this->rider->phone) {
            $mail->line("You can call them on **{$this->rider->phone}** if you need to.");
        }
        if ($this->code) {
            $mail->line("Your delivery code is **{$this->code}** — read it to {$name} when they arrive.");
        }

        return $mail->line('You can also follow it under Your orders.')->salutation('— '.Branding::name());
    }
}
