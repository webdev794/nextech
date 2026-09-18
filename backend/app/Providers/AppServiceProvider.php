<?php

namespace App\Providers;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // barryvdh/laravel-dompdf defaults to base_path('public') for its asset
        // base path. The cPanel bundle deploys app + web root as one folder
        // (public/ is deleted, index.php calls usePublicPath(__DIR__)) — so
        // that default no longer exists on disk and dompdf's realpath() check
        // throws "Cannot resolve public path". public_path() always reflects
        // whatever usePublicPath() set, so it stays correct in both layouts.
        config(['dompdf.public_path' => public_path()]);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
       // $this->ensureCaBundle();
       // $this->applyStoredStripeCredentials();
        if (! $this->app->runningInConsole()) {
            $this->applyStoredStripeCredentials();
        }
    }

    /**
     * Let an administrator change the Stripe keys at runtime (Admin console ->
     * Payments) without editing .env: overlay any saved key onto the config so
     * every existing config('services.stripe.*') read picks it up.
     */
    private function applyStoredStripeCredentials(): void
    {
        try {
            if (! Schema::hasTable('settings')) {
                return;
            }
        } catch (\Throwable) {
            return;
        }

        $saved = Setting::get('payments', []);
        if (! is_array($saved)) {
            return;
        }

        foreach ([
            'stripe_key' => 'services.stripe.key',
            'stripe_secret' => 'services.stripe.secret',
            'stripe_webhook_secret' => 'services.stripe.webhook_secret',
        ] as $key => $path) {
            if (! empty($saved[$key])) {
                config([$path => $saved[$key]]);
            }
        }
    }

    /**
     * XAMPP / bare PHP on Windows often ships without curl.cainfo or
     * openssl.cafile set, which makes every outbound HTTPS request fail with
     * "unable to get local issuer certificate". Fall back to the CA bundle
     * committed at resources/certs/cacert.pem so geocoding and other API calls
     * work out of the box. A PHP install with a real CA bundle is unaffected.
     */
    private function ensureCaBundle(): void
    {
        if (ini_get('openssl.cafile') || ini_get('curl.cainfo')) {
            return;
        }

        $bundle = resource_path('certs/cacert.pem');
        if (! is_file($bundle)) {
            return;
        }

        Http::globalOptions(['verify' => $bundle]);
        putenv("CURL_CA_BUNDLE={$bundle}");
        putenv("SSL_CERT_FILE={$bundle}");
        $_SERVER['CURL_CA_BUNDLE'] = $bundle;
    }
}
