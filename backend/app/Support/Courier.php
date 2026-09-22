<?php

namespace App\Support;

use App\Models\Order;
use App\Models\Shipment;
use App\Support\Courier\CourierProvider;
use App\Support\Courier\CourierProviderUnavailableException;
use App\Support\Courier\MockCourierProvider;
use App\Support\Courier\RealCourierProvider;
use Illuminate\Support\Facades\Log;

/**
 * Static-helper facade over the configured courier provider (config/courier.php),
 * matching the Geo/CheckoutFees convention rather than a container-bound
 * interface. The passthroughs also own the Shipment persistence so callers
 * never touch the provider or the model directly.
 *
 * Every passthrough falls back to a fresh MockCourierProvider whenever the
 * configured provider can't answer (not configured, unreachable, unrecognized
 * config value, ...) so a real-provider hiccup never breaks checkout/booking.
 */
class Courier
{
    public static function provider(): CourierProvider
    {
        return match (config('courier.provider', 'mock')) {
            'real' => new RealCourierProvider(CourierCredentials::current()),
            default => new MockCourierProvider(),
        };
    }

    /** @param  array<string, mixed>  $address */
    public static function isServiceable(array $address): bool
    {
        [$result] = self::withFallback(fn (CourierProvider $provider) => $provider->isServiceable($address));

        return $result;
    }

    /**
     * @param  array<string, mixed>  $address
     * @return array{cost_cents: int, eta_days: int}
     */
    public static function quote(array $address): array
    {
        [$result] = self::withFallback(fn (CourierProvider $provider) => $provider->quote($address));

        return $result;
    }

    /** Books the shipment with the provider and persists it as the order's Shipment row. */
    public static function book(Order $order): Shipment
    {
        [$booking, $providerName] = self::withFallback(fn (CourierProvider $provider) => $provider->book($order));

        return $order->shipment()->updateOrCreate([], [
            // The provider that actually handled the booking, not just the
            // configured one — a fallen-back-to-mock booking must not read as
            // "real" later, or nobody will notice a real shipment never happened.
            'provider' => $providerName,
            'tracking_number' => $booking['tracking_number'],
            'carrier' => $booking['carrier'],
            'label_url' => $booking['label_url'] ?? null,
            'status' => 'booked',
            'cost_cents' => (int) $order->delivery_fee_cents,
            'booked_at' => now(),
        ]);
    }

    /** Pulls the provider's current status for a shipment and saves it. */
    public static function track(Shipment $shipment): Shipment
    {
        [$status] = self::withFallback(fn (CourierProvider $provider) => $provider->track($shipment));
        $shipment->update(['status' => $status]);

        return $shipment;
    }

    /**
     * Runs $call against the configured provider; if it throws (not
     * configured, unreachable, bad response, anything), logs a warning and
     * re-runs the same call against a fresh MockCourierProvider instead.
     * Returns which provider actually served the call alongside the result,
     * so callers that persist a provider name (book()) record the truth even
     * when a "real" attempt silently fell back.
     *
     * @template T
     * @param  \Closure(CourierProvider): T  $call
     * @return array{0: T, 1: string}
     */
    private static function withFallback(\Closure $call): array
    {
        $provider = self::provider();
        $name = (string) config('courier.provider', 'mock');

        if ($provider instanceof MockCourierProvider) {
            return [$call($provider), 'mock'];
        }

        try {
            return [$call($provider), $name];
        } catch (CourierProviderUnavailableException|\Throwable $e) {
            Log::warning('Courier provider unavailable, falling back to mock.', [
                'provider' => $name,
                'error' => $e->getMessage(),
            ]);

            return [$call(new MockCourierProvider()), 'mock'];
        }
    }
}
