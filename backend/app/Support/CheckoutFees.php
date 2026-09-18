<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Effective checkout charges: the config/checkout.php defaults with any admin
 * overrides (settings -> "checkout_fees") laid on top. The admin edits these
 * from the panel; env vars are just the initial values.
 */
class CheckoutFees
{
    /**
     * @return array<string, int|string>
     */
    public static function current(): array
    {
        $defaults = [
            'delivery_mode' => (string) config('checkout.delivery_mode', 'fixed'),
            'delivery_fee_cents' => (int) config('checkout.delivery_fee_cents'),
            'delivery_near_fee_cents' => (int) config('checkout.delivery_near_fee_cents'),
            'delivery_far_fee_cents' => (int) config('checkout.delivery_far_fee_cents'),
            'free_delivery_threshold_cents' => (int) config('checkout.free_delivery_threshold_cents'),
            'handling_fee_cents' => (int) config('checkout.handling_fee_cents'),
            'small_cart_fee_cents' => (int) config('checkout.small_cart_fee_cents'),
            'small_cart_min_cents' => (int) config('checkout.small_cart_min_cents'),
            'tax_rate_bps' => (int) config('checkout.tax_rate_bps'),
        ];

        $override = Setting::get('checkout_fees', []);
        $override = is_array($override) ? array_intersect_key($override, $defaults) : [];

        $merged = array_merge($defaults, $override);
        $merged['delivery_mode'] = $merged['delivery_mode'] === 'distance' ? 'distance' : 'fixed';

        foreach ($merged as $key => $value) {
            if ($key !== 'delivery_mode') {
                $merged[$key] = max(0, (int) $value);
            }
        }

        return $merged;
    }

    /**
     * The delivery fee for a point, before the free-delivery waiver.
     *
     * @param  array<string, int|string>  $fees
     */
    public static function distanceFeeCents(array $fees, ?float $km, ?float $radiusKm): int
    {
        if (($fees['delivery_mode'] ?? 'fixed') !== 'distance') {
            return (int) $fees['delivery_fee_cents'];
        }

        $near = (int) $fees['delivery_near_fee_cents'];
        $far = (int) $fees['delivery_far_fee_cents'];

        // No coordinates (or no radius) => assume the worst case.
        if ($km === null || $radiusKm === null || $radiusKm <= 0) {
            return $far;
        }

        $t = max(0.0, min(1.0, $km / $radiusKm));

        return (int) round($near + $t * ($far - $near));
    }

    /**
     * The delivery fee charged on an order: the distance fee, waived to 0 once
     * the subtotal reaches the free-delivery threshold.
     *
     * @param  array<string, int|string>  $fees
     */
    public static function deliveryFeeCents(array $fees, int $subtotalCents, ?float $km, ?float $radiusKm): int
    {
        if ($subtotalCents >= (int) $fees['free_delivery_threshold_cents']) {
            return 0;
        }

        return self::distanceFeeCents($fees, $km, $radiusKm);
    }
}
