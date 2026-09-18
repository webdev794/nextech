<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RiderAssigned extends Notification
{
    use Queueable;

    public function __construct(public int $orderId, public ?string $area = null) {}

    public static function forOrder(Order $order): self
    {
        $address = $order->delivery_address ?? [];
        $area = trim(implode(', ', array_filter([
            $address['city'] ?? null,
            $address['state'] ?? null,
        ]))) ?: null;

        return new self($order->id, $area);
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject("New delivery assigned — order #{$this->orderId}")
            ->line("You've been assigned order #{$this->orderId}.");

        if ($this->area) {
            $mail->line("Delivering to: {$this->area}.");
        }

        return $mail->line('Open your Deliveries screen to see the address and items.');
    }
}
