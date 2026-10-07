<?php

namespace App\Listeners;

use App\Models\CustomerEmail;
use App\Models\User;
use App\Support\CustomerMail;
use Illuminate\Mail\SentMessage;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mime\Email;

/**
 * Records every email notification that was sent (order emails, codes, rider
 * messages, custom and campaign emails) in customer_emails, so the admin CRM
 * can show a customer's full email history. Never interferes with sending.
 */
class LogCustomerEmail
{
    public function handle(NotificationSent $event): void
    {
        if ($event->channel !== 'mail') {
            return;
        }

        try {
            $notification = $event->notification;
            $notifiable = $event->notifiable;

            $to = method_exists($notifiable, 'routeNotificationFor') ? $notifiable->routeNotificationFor('mail', $notification) : null;
            if (is_array($to)) {
                $first = array_key_first($to);
                $to = is_string($first) && str_contains($first, '@') ? $first : reset($to);
            }
            $to = (string) $to;

            $user = $notifiable instanceof User ? $notifiable : ($to !== '' ? User::where('email', $to)->first() : null);

            $message = $event->response instanceof SentMessage ? $event->response->getOriginalMessage() : null;

            CustomerEmail::create([
                'user_id' => $user?->id,
                'to_email' => $to,
                'kind' => CustomerMail::kindFor($notification),
                'subject' => $message instanceof Email ? $message->getSubject() : null,
                'body_html' => $message instanceof Email ? $message->getHtmlBody() : null,
                'order_id' => CustomerMail::orderIdFor($notification),
                'campaign_id' => $notification->campaignId ?? null,
                'status' => 'sent',
                'sent_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Could not log customer email.', ['error' => $e->getMessage()]);
        }
    }
}
