<?php

namespace App\Support\Courier;

use App\Models\Order;
use App\Models\Shipment;

/**
 * A pluggable third-party carrier. MockCourierProvider is the only
 * implementation today; a real integration (Bluedart/FedEx/etc.) plugs in
 * behind this same interface without touching the callers.
 */
interface CourierProvider
{
    /** @param  array<string, mixed>  $address */
    public function isServiceable(array $address): bool;

    /**
     * @param  array<string, mixed>  $address
     * @return array{cost_cents: int, eta_days: int}
     */
    public function quote(array $address): array;

    /** @return array{tracking_number: string, carrier: string, label_url: ?string} */
    public function book(Order $order): array;

    /** Current status for a booked shipment: booked|in_transit|delivered|failed. */
    public function track(Shipment $shipment): string;
}
