<?php

namespace App\Support;

use App\Models\Setting;

class CourierCredentials
{
    /**
     * Effective real-courier-provider credentials: admin-saved (Admin console
     * -> Secure access -> Courier) values only. Unlike Payments::stripe(),
     * there is no env-var fallback layer here — no specific carrier has been
     * chosen yet, so there's no sensible env default to fall back to, and the
     * admin-UI form is the intended (and only) way these get set.
     *
     * @return array{provider: string, base_url: string, api_key: string, api_secret: string, account_code: string, tracking_api_key: string, tracking_webhook_secret: string}
     */
    public static function current(): array
    {
        $saved = Setting::get('courier', []);
        $saved = is_array($saved) ? $saved : [];

        $pick = fn (string $key): string => trim((string) ($saved[$key] ?? ''));

        return [
            'provider' => $pick('courier_provider') !== '' ? $pick('courier_provider') : 'mock',
            'base_url' => $pick('courier_base_url'),
            'api_key' => $pick('courier_api_key'),
            'api_secret' => $pick('courier_api_secret'),
            'account_code' => $pick('courier_account_code'),
            // Live tracking for sellers' own-courier packages (AfterShip — LiveTracking).
            'tracking_api_key' => $pick('tracking_api_key'),
            'tracking_webhook_secret' => $pick('tracking_webhook_secret'),
        ];
    }
}
