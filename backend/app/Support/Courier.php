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
        $origin = self::originAddress($order);

        [$booking, $providerName] = self::withFallback(fn (CourierProvider $provider) => $provider->book($order, $origin));

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

    /**
     * Buy a shipping label on NexTech's courier account for a seller's own
     * package (the seller ships it; NexTech just supplies the label). Returns
     * the booking plus what it cost, which the caller charges to the seller.
     *
     * @param  array<string, mixed>  $origin  the seller's ship-from address
     * @return array{tracking_number: string, carrier: string, label_url: ?string, cost_cents: int}
     */
    public static function buyLabel(Order $order, array $origin): array
    {
        [$booking] = self::withFallback(fn (CourierProvider $provider) => $provider->book($order, $origin));
        $cost = self::quote((array) $order->delivery_address)['cost_cents'];

        return [
            'tracking_number' => $booking['tracking_number'],
            'carrier' => $booking['carrier'],
            'label_url' => $booking['label_url'] ?? null,
            'cost_cents' => (int) $cost,
        ];
    }

    /**
     * Current status of a NexTech-bought label, mapped to package statuses
     * (shipped | in_transit | delivered | lost). Uses an unsaved Shipment as
     * the provider's input so label packages share the same tracking path.
     */
    public static function trackLabel(string $carrier, string $trackingNumber, ?\DateTimeInterface $shippedAt, string $currentStatus): string
    {
        $probe = new Shipment([
            'carrier' => $carrier,
            'tracking_number' => $trackingNumber,
            'booked_at' => $shippedAt,
            'status' => match ($currentStatus) { 'delivered' => 'delivered', 'lost' => 'failed', 'in_transit' => 'in_transit', default => 'booked' },
        ]);
        [$status] = self::withFallback(fn (CourierProvider $provider) => $provider->track($probe));

        return match ($status) { 'delivered' => 'delivered', 'failed' => 'lost', 'in_transit' => 'in_transit', default => 'shipped' };
    }

    /** Pulls the provider's current status for a shipment and saves it. */
    public static function track(Shipment $shipment): Shipment
    {
        [$status] = self::withFallback(fn (CourierProvider $provider) => $provider->track($shipment));
        $shipment->update(['status' => $status]);

        return $shipment;
    }

    /**
     * Where the courier should collect this order from: the seller's pickup
     * address for a marketplace order (first shop item — a shipment is one
     * per order, so a mixed-vendor cart picks up from a single origin same
     * as the rest of the shipment model), falling back to the fulfilling
     * store's address for NexTech's own inventory.
     */
    private static function originAddress(Order $order): array
    {
        $shop = $order->items()->whereNotNull('shop_id')->where('fulfilled_by', 'nextech')->with('shop.seller')->first()?->shop;

        // A default ship-from address set in Shipping settings wins over the
        // pickup address from the seller application.
        $default = $shop?->addresses()->where('is_default', true)->first();
        if ($default) {
            return $default->toCourierAddress();
        }

        if ($shop?->seller) {
            return $shop->seller->pickupAddress();
        }

        $store = $order->store;

        return [
            'phone' => null,
            'line1' => $store?->line1,
            'line2' => $store?->line2,
            'city' => $store?->city,
            'state' => $store?->state,
            'postal_code' => $store?->postal_code,
            'country' => $store?->country,
        ];
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
