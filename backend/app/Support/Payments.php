<?php

namespace App\Support;

use App\Models\Setting;

class Payments
{
    /**
     * Effective Stripe credentials: an admin-saved value (Admin console ->
     * Payments) wins, otherwise the STRIPE_* env / config value.
     *
     * @return array{key: string, secret: string, webhook_secret: string, mode: string}
     */
    public static function stripe(): array
    {
        $saved = Setting::get('payments', []);
        $saved = is_array($saved) ? $saved : [];

        $pick = fn (string $key, string $configPath): string => trim((string) (
            ($saved[$key] ?? '') !== '' ? $saved[$key] : config($configPath)
        ));

        $secret = $pick('stripe_secret', 'services.stripe.secret');

        return [
            'key' => $pick('stripe_key', 'services.stripe.key'),
            'secret' => $secret,
            'webhook_secret' => $pick('stripe_webhook_secret', 'services.stripe.webhook_secret'),
            'mode' => str_starts_with($secret, 'sk_live_') ? 'live' : 'test',
        ];
    }
}
