<?php

namespace App\Notifications;

use App\Support\Branding;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Markdown;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\HtmlString;

/**
 * An email written by an admin (to one customer, or a campaign). The body is
 * markdown with {name} / {first_name} placeholders; promotional emails get an
 * unsubscribe link.
 */
class CustomEmail extends Notification
{
    use Queueable;

    public function __construct(
        public string $subjectLine,
        public string $body,
        public ?int $campaignId = null,
        public bool $promotional = false,
        public bool $toOneCustomer = false, // logged as "custom" rather than "campaign"
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $brand = Branding::current();
        $brandName = ($brand['store_name'] ?? '') !== '' ? $brand['store_name'] : config('app.name');
        $name = trim((string) ($notifiable->name ?? ''));
        $first = $name !== '' ? explode(' ', $name)[0] : 'there';
        $fill = fn (string $text) => strtr($text, ['{name}' => $name !== '' ? $name : 'there', '{first_name}' => $first]);

        $mail = (new MailMessage)
            ->subject($fill($this->subjectLine))
            ->line(new HtmlString(Markdown::parse($fill($this->body))->toHtml()))
            ->salutation("— {$brandName}");

        if ($this->promotional && isset($notifiable->id)) {
            $url = URL::signedRoute('unsubscribe', ['user' => $notifiable->id]);
            $mail->line(new HtmlString('<small>Don\'t want these emails? <a href="'.e($url).'">Unsubscribe</a>.</small>'));
        }

        return $mail;
    }
}
