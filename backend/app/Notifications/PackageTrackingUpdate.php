<?php

namespace App\Notifications;

use App\Models\Order;
use App\Models\OrderPackage;
use App\Support\Branding;
use App\Support\LiveTracking;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** To the buyer when live tracking says a seller-shipped package is out for delivery, or has a problem. */
class PackageTrackingUpdate extends Notification
{
    use Queueable;

    public function __construct(public Order $order, public OrderPackage $package) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $brand = Branding::current();
        $brandName = ($brand['store_name'] ?? '') !== '' ? $brand['store_name'] : config('app.name');
        $status = LiveTracking::label($this->package->tracking_tag) ?? 'Update';

        $mail = (new MailMessage)
            ->subject("Order #{$this->order->id}: {$status} — {$brandName}")
            ->greeting($status)
            ->line("Your package from order #{$this->order->id} ({$this->package->carrier_label} {$this->package->tracking_number}):");
        if ($this->package->tracking_detail) {
            $mail->line($this->package->tracking_detail);
        }
        if ($this->package->tracking_url) {
            $mail->action('Track with the courier', $this->package->tracking_url);
        }

        return $mail->line('You can follow it any time under Your orders.')->salutation("— {$brandName}");
    }
}
