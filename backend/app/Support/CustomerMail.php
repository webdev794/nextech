<?php

namespace App\Support;

use App\Models\CustomerEmail;
use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\CustomEmail;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Customer emails for the CRM: what kind each notification is, which order it
 * belongs to, and sending that never breaks the caller (a failed send is
 * logged as "failed" instead of throwing into checkout or delivery).
 */
class CustomerMail
{
    /** notification class => log kind */
    private const KINDS = [
        \App\Notifications\SendOtpCode::class => 'otp',
        \App\Notifications\DeliveryHandoverCode::class => 'delivery_code',
        \App\Notifications\RiderMessage::class => 'rider_message',
        \App\Notifications\OrderDelivered::class => 'order_delivered',
        \App\Notifications\OrderConfirmed::class => 'order_confirmed',
        \App\Notifications\OrderShipped::class => 'order_shipped',
        \App\Notifications\RiderAssigned::class => 'rider_assigned',
    ];

    public static function kindFor(Notification $notification): string
    {
        if ($notification instanceof CustomEmail) {
            return $notification->campaignId && ! $notification->toOneCustomer ? 'campaign' : 'custom';
        }

        return self::KINDS[$notification::class] ?? Str::snake(class_basename($notification));
    }

    public static function orderIdFor(Notification $notification): ?int
    {
        if (isset($notification->order) && $notification->order instanceof Order) {
            return $notification->order->id;
        }

        return isset($notification->orderId) ? (int) $notification->orderId : null;
    }

    /** Order confirmed / shipped emails can be switched off in Admin → Settings. */
    public static function orderEmailsEnabled(): bool
    {
        return (bool) Setting::get('order_emails', true);
    }

    /**
     * Send to a customer; a failure is logged, never thrown. Successful sends
     * are logged by App\Listeners\LogCustomerEmail.
     */
    public static function send(User $user, Notification $notification): bool
    {
        try {
            $user->notify($notification);

            return true;
        } catch (\Throwable $e) {
            Log::warning('Customer email failed.', ['user' => $user->id, 'notification' => $notification::class, 'error' => $e->getMessage()]);
            try {
                CustomerEmail::create([
                    'user_id' => $user->id,
                    'to_email' => (string) $user->email,
                    'kind' => self::kindFor($notification),
                    'order_id' => self::orderIdFor($notification),
                    'campaign_id' => $notification->campaignId ?? null,
                    'status' => 'failed',
                    'error' => Str::limit($e->getMessage(), 490),
                ]);
            } catch (\Throwable) {
                // logging must never break the caller either
            }

            return false;
        }
    }
}
