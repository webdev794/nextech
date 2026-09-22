<?php

namespace App\Support\Courier;

use App\Models\Order;
use App\Models\Shipment;
use Illuminate\Support\Str;

/**
 * Always-serviceable stand-in for a real carrier — flat quote, a fake
 * tracking number, and a status that advances deterministically with elapsed
 * time since booking so admin testing never needs a manual state flip.
 */
class MockCourierProvider implements CourierProvider
{
    private const QUOTE_COST_CENTS = 999;

    private const QUOTE_ETA_DAYS = 4;

    private const IN_TRANSIT_AFTER_MINUTES = 1;

    private const DELIVERED_AFTER_MINUTES = 2;

    public function isServiceable(array $address): bool
    {
        return true;
    }

    public function quote(array $address): array
    {
        return [
            'cost_cents' => self::QUOTE_COST_CENTS,
            'eta_days' => self::QUOTE_ETA_DAYS,
        ];
    }

    public function book(Order $order): array
    {
        return [
            'tracking_number' => 'MOCK-'.strtoupper(Str::random(10)),
            'carrier' => 'MockCourier',
            'label_url' => null,
        ];
    }

    public function track(Shipment $shipment): string
    {
        if (in_array($shipment->status, ['delivered', 'failed'], true)) {
            return $shipment->status;
        }

        $minutes = $shipment->booked_at ? $shipment->booked_at->diffInMinutes(now()) : 0;

        if ($minutes >= self::DELIVERED_AFTER_MINUTES) {
            return 'delivered';
        }

        if ($minutes >= self::IN_TRANSIT_AFTER_MINUTES) {
            return 'in_transit';
        }

        return 'booked';
    }
}
