<?php

namespace App\Support;

use App\Models\Seller;
use Illuminate\Support\Facades\Cache;
use Stripe\StripeClient;

/**
 * Stripe payouts for sellers (Stripe Connect, Accounts v2): each seller gets a
 * Stripe "recipient" account they set up on Stripe's own pages (identity and
 * bank), and admin's payout is a transfer from the store's Stripe balance.
 * Stripe only lets a platform create these in its own country.
 */
class StripeConnect
{
    private static function client(): StripeClient
    {
        return new StripeClient((string) config('services.stripe.secret'));
    }

    /** Why Stripe payouts can't be offered in $market, or null when they can. */
    public static function unavailable(?string $market): ?string
    {
        if (! config('services.stripe.secret')) {
            return 'Add the Stripe keys first (Secure access → Payments — Stripe).';
        }
        $platform = self::platformCountry();
        $country = strtoupper((string) ($market ?? Market::home()));
        if ($platform && $country !== $platform) {
            return 'Stripe can only pay sellers in your Stripe account’s own country ('.$platform.') — sellers in '.$country.' need bank transfer or PayPal.';
        }

        return null;
    }

    /** The country of the store's own Stripe account (cached for a day; null when Stripe can't be reached). */
    public static function platformCountry(): ?string
    {
        $secret = (string) config('services.stripe.secret');
        if ($secret === '') {
            return null;
        }

        return Cache::remember('stripe_platform_country:'.substr(hash('sha256', $secret), 0, 12), now()->addDay(), function () {
            try {
                return strtoupper((string) self::client()->accounts->retrieve()->country) ?: null;
            } catch (\Throwable $e) {
                report($e);

                return null;
            }
        }) ?: null;
    }

    /** A link to Stripe's onboarding pages for this seller (creating their Stripe account the first time). */
    public static function onboardingLink(Seller $seller, string $returnUrl): string
    {
        $stripe = self::client();
        if (! $seller->stripe_account_id) {
            $account = $stripe->v2->core->accounts->create([
                'contact_email' => $seller->user?->email,
                'display_name' => $seller->shop?->name ?? $seller->user?->name,
                'dashboard' => 'express',
                'identity' => ['country' => strtolower((string) ($seller->shop?->market ?? Market::home()))],
                'configuration' => ['recipient' => ['capabilities' => ['stripe_balance' => ['stripe_transfers' => ['requested' => true]]]]],
                'defaults' => ['responsibilities' => ['fees_collector' => 'application', 'losses_collector' => 'application']],
                'metadata' => ['seller_id' => (string) $seller->id],
            ]);
            $seller->forceFill(['stripe_account_id' => $account->id, 'stripe_ready' => false])->save();
        }
        // The query goes before the "#/seller" part so the page still opens Seller Center.
        [$base, $hash] = array_pad(explode('#', $returnUrl, 2), 2, null);
        $with = fn (string $v) => $base.(str_contains($base, '?') ? '&' : '?').'stripe='.$v.($hash !== null ? '#'.$hash : '');

        return $stripe->v2->core->accountLinks->create([
            'account' => $seller->stripe_account_id,
            'use_case' => ['type' => 'account_onboarding', 'account_onboarding' => [
                'configurations' => ['recipient'],
                'refresh_url' => $with('refresh'),
                'return_url' => $with('return'),
            ]],
        ])->url;
    }

    /** Ask Stripe whether the seller's account can receive transfers yet; saves and returns it. */
    public static function refresh(Seller $seller): bool
    {
        if (! $seller->stripe_account_id) {
            return false;
        }
        $account = self::client()->v2->core->accounts->retrieve($seller->stripe_account_id, ['include' => ['configuration.recipient']]);
        $ready = ($account->configuration?->recipient?->capabilities?->stripe_balance?->stripe_transfers?->status ?? null) === 'active';
        $seller->forceFill(['stripe_ready' => $ready])->save();

        return $ready;
    }

    /** Send $cents (USD) to the seller's Stripe account. Returns the transfer id. */
    public static function transfer(Seller $seller, int $cents, string $currency, string $description, string $idempotencyKey): string
    {
        return self::client()->transfers->create([
            'amount' => $cents,
            'currency' => strtolower($currency),
            'destination' => $seller->stripe_account_id,
            'description' => $description,
            'metadata' => ['seller_id' => (string) $seller->id],
        ], ['idempotency_key' => $idempotencyKey])->id;
    }
}
