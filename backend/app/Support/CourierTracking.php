<?php

namespace App\Support;

use App\Models\Order;

/**
 * Pulls an online-courier order's tracking and completes the order once the
 * courier reports it delivered — no handover code to check for a third-party
 * courier. Used by the admin "Sync tracking" button and the 30-minute schedule.
 */
class CourierTracking
{
    public static function sync(Order $order): bool
    {
        // A courier admin booked by hand isn't tracked through the courier connection — admin marks it delivered.
        if (! $order->usesOnlineCourier() || ! $order->shipment || $order->shipment->provider === 'manual') {
            return false;
        }

        $shipment = Courier::track($order->shipment);

        if ($shipment->status === 'delivered' && $order->canTransitionTo('completed')) {
            $order->forceFill([
                'status' => 'completed',
                'delivered_at' => now(),
                'delivery_verified' => false,
                'delivery_note' => "Delivered by online courier ({$shipment->carrier} {$shipment->tracking_number}).",
                'rider_offer_expires_at' => null,
            ])->save();
            $order->refresh()->sendDeliveredReceiptIfReady();

            return true;
        }

        return false;
    }
}
