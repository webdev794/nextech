<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\SellerShipping;
use App\Support\SellerFulfillment;
use App\Models\OrderPackage;
use App\Models\Order;
use App\Models\User;
use App\Notifications\RiderAssigned;
use App\Support\Courier;
use App\Support\CustomerNames;
use App\Support\DeliveryOfferSweeper;
use App\Support\RiderAssignment;
use App\Support\SellerLedger;
use App\Support\Market;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminOrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['sometimes', 'string'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:1000'],
        ]);

        // Loading the orders board also drives the offer-timeout sweep.
        try {
            DeliveryOfferSweeper::sweep();
        } catch (\Throwable $e) {
            report($e);
        }

        $orders = Order::query()
            ->where('market', Market::fromRequest($request))
            ->with([
                'items', 'user:id,name,email,phone', 'deliveryPartner:id,name', 'store:id,name,city', 'shipment',
            'packages.items', 'packages.shop:id,name', 'shopShipping.shop:id,name', 'labelRequests',
                'riderReview:id,order_id,rating,comment,source',
                'supportThreads:id,order_id,rating,rating_comment',
                'giftCards:id,order_id,code,initial_cents,balance_cents,reason,issued_by,created_at',
                'giftCards.issuedBy:id,name',
                'refunds.creator:id,name',
            ])
            ->when($validated['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->latest()
            ->paginate($validated['per_page'] ?? 10);

        $this->attachCustomerNames($orders->items());

        return response()->json([
            'data' => $orders->items(),
            'meta' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
            ],
        ]);
    }

    public function show(Order $order): JsonResponse
    {
        $order->load([
            'items', 'user:id,name,email,phone', 'store:id,name,city', 'shipment',
            'riderReview:id,order_id,rating,comment,source',
            'supportThreads:id,order_id,rating,rating_comment',
            'giftCards:id,order_id,code,initial_cents,balance_cents,reason,issued_by,created_at',
            'giftCards.issuedBy:id,name',
            'refunds.creator:id,name',
        ]);
        $this->attachCustomerNames([$order]);

        return response()->json(['data' => $order]);
    }

    public function update(Request $request, Order $order): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['sometimes', Rule::in(array_keys(Order::DELIVERY_TRANSITIONS))],
            'courier_name' => ['sometimes', 'nullable', 'string', 'max:120'],
            'delivery_partner_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'cash_collected' => ['sometimes', 'boolean'],
            'refunded' => ['sometimes', 'boolean'],
            'items_returned' => ['sometimes', 'boolean'],
        ]);

        if (! array_key_exists('status', $validated)
            && ! array_key_exists('courier_name', $validated)
            && ! array_key_exists('delivery_partner_id', $validated)
            && ! array_key_exists('cash_collected', $validated)
            && ! array_key_exists('refunded', $validated)
            && ! array_key_exists('items_returned', $validated)) {
            return response()->json(['message' => 'Provide a status change, a courier assignment, or a payment update.'], 422);
        }

        // A seller-shipped-only order moves with its packages (see
        // SellerFulfillment::sync) — NexTech doesn't pack or deliver it.
        if (isset($validated['status']) && $order->isSellerShippedOnly()
            && in_array($validated['status'], ['packing', 'ready_for_delivery', 'out_for_delivery', 'completed'], true)) {
            return response()->json([
                'message' => 'The seller ships this order themselves — it updates as they confirm shipment and delivery. You can still cancel it or mark packages delivered.',
            ], 422);
        }

        if (isset($validated['status']) && ! $order->canTransitionTo($validated['status'])) {
            return response()->json([
                'message' => "An order that is {$order->status} cannot move to {$validated['status']}.",
            ], 422);
        }

        $changes = array_intersect_key($validated, array_flip(['status', 'courier_name']));

        // Assigning a delivery partner: must be a rider, and it also fills the
        // display courier_name. Clearing the rider (null) only wipes the display
        // name when a replacement name wasn't sent in the same request — so
        // "type a courier name + Save" keeps the typed name.
        if (array_key_exists('delivery_partner_id', $validated)) {
            $rider = $validated['delivery_partner_id']
                ? User::find($validated['delivery_partner_id'])
                : null;

            if ($validated['delivery_partner_id'] && ! $rider?->is_rider) {
                return response()->json(['message' => 'That user is not a delivery rider.'], 422);
            }

            $changes['delivery_partner_id'] = $rider?->id;
            if ($rider) {
                $changes['courier_name'] = $rider->name;
            } elseif (! array_key_exists('courier_name', $validated)) {
                $changes['courier_name'] = null;
            }

            $riderChanged = $rider && $rider->id !== $order->delivery_partner_id;

            if ($riderChanged) {
                // A hand-picked rider still gets the Accept/Reject prompt. The
                // 60s clock only starts once the order is actually ready to go
                // out — assigning during packing is a soft pre-assignment. The
                // decline history is deliberately kept (the admin's pick still
                // goes through — it bypasses the auto-assign exclusion).
                $changes['rider_accepted_at'] = null;
                $effectiveStatus = $changes['status'] ?? $order->status;
                if ($effectiveStatus === 'ready_for_delivery') {
                    $changes['rider_offer_expires_at'] = now()->addSeconds(RiderAssignment::OFFER_TTL_SECONDS);
                    User::whereKey($rider->id)->increment('rider_offers_count');
                } else {
                    $changes['rider_offer_expires_at'] = null;
                }
            } elseif (! $rider) {
                // Clearing the rider ends any pending offer.
                $changes['rider_offer_expires_at'] = null;
                $changes['rider_accepted_at'] = null;
            }
        }

        // Turning a soft pre-assignment into a live offer: the order becomes
        // ready for delivery while already carrying an unaccepted rider.
        if (! array_key_exists('delivery_partner_id', $validated)
            && ($changes['status'] ?? null) === 'ready_for_delivery'
            && $order->delivery_partner_id
            && $order->rider_accepted_at === null) {
            $changes['rider_offer_expires_at'] = now()->addSeconds(RiderAssignment::OFFER_TTL_SECONDS);
            User::whereKey($order->delivery_partner_id)->increment('rider_offers_count');
        }

        // Cash collected on hand-off settles a cash-on-delivery order.
        if (($validated['cash_collected'] ?? false)
            && $order->isCashOnDelivery()
            && $order->payment_status !== 'paid') {
            $changes['payment_status'] = 'paid';
            $changes['cash_collected_at'] = now();
        }

        // Cancelling an order before any money moved also voids its payment —
        // an abandoned card checkout or an unpaid cash-on-delivery order.
        if (($changes['status'] ?? null) === 'cancelled'
            && $order->payment_status === 'pending') {
            $changes['payment_status'] = 'cancelled';
        }

        if (($changes['status'] ?? null) === 'cancelled') {
            $changes['cancelled_by'] = 'admin';
        }

        // A gift card covered the whole order — restoring its balance below
        // already is the full refund, so there's nothing left for an admin
        // to action.
        if (($changes['status'] ?? null) === 'cancelled' && $order->wasFullyCoveredByGiftCard()) {
            $changes['payment_status'] = 'refunded';
        }

        // Admin has issued the refund for a customer-cancelled paid order.
        if (($validated['refunded'] ?? false) && $order->payment_status === 'refund_pending') {
            $changes['payment_status'] = 'refunded';
        }

        // Store confirms the rider brought back the items from a refused-COD
        // cancellation — clears the reminder from the rider's dashboard.
        if (($validated['items_returned'] ?? false) && $order->needsItemReturn()) {
            $changes['items_returned_at'] = now();
        }

        $previousRiderId = $order->delivery_partner_id;
        $becamePaid = ($changes['payment_status'] ?? null) === 'paid';

        $order->update($changes);

        if ($becamePaid) {
            SellerLedger::creditForOrder($order);
        }

        // Cancelling voids any gift-card balance spent on this order at checkout.
        if (($changes['status'] ?? null) === 'cancelled') {
            $order->restoreGiftCardRedemptions();
        }

        // Tell a rider the moment they're put on an order by hand (auto-assign
        // notifies from RiderAssignment). Only on an actual change of rider.
        if (array_key_exists('delivery_partner_id', $changes)
            && $changes['delivery_partner_id']
            && $changes['delivery_partner_id'] !== $previousRiderId
            && isset($rider)) {
            $rider->notify(RiderAssigned::forOrder($order));
        }

        // An order that just became ready for delivery is handed off per its
        // delivery method: an online-courier order is booked with the courier
        // (and — since there's no separate pickup step for a third party —
        // goes straight out for delivery); an own-rider order with none yet
        // gets one auto-assigned, or drops into the first-come pool as before.
        if (($changes['status'] ?? null) === 'ready_for_delivery') {
            if ($order->usesOnlineCourier()) {
                if (! $order->shipment) {
                    $shipment = Courier::book($order);
                    $order->update(['courier_name' => $shipment->carrier, 'status' => 'out_for_delivery']);
                }
            } elseif (! $order->delivery_partner_id) {
                RiderAssignment::assign($order);
            }
        }

        // Marking delivered, or collecting cash on a delivered order, sends the
        // customer their summary email with the PDF bill.
        $order->refresh()->sendDeliveredReceiptIfReady();

        $fresh = $this->detail($order);

        return response()->json(['data' => $fresh]);
    }

    /**
     * Pull the courier's current status for this order's shipment. A status of
     * `delivered` completes the order the same way the rider app's override
     * completion does — no handover code to check for a third-party courier.
     */
    public function syncTracking(Order $order): JsonResponse
    {
        if (! $order->usesOnlineCourier() || ! $order->shipment) {
            return response()->json(['message' => 'This order has no online-courier shipment to track.'], 422);
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
        }

        return response()->json(['data' => $this->detail($order)]);
    }

    /**
     * Manual escalation for the "no rider ever available" case: an own-rider
     * order sitting unassigned in the ready-for-delivery pool is handed off to
     * the online courier instead, deliberately by hand rather than on a timer.
     */
    public function escalateToCourier(Order $order): JsonResponse
    {
        if ($order->usesOnlineCourier() || $order->status !== 'ready_for_delivery' || $order->delivery_partner_id) {
            return response()->json(['message' => 'Only an unassigned, ready-for-delivery own-rider order can be sent via online courier.'], 422);
        }

        $order->update(['delivery_method' => 'online_courier']);
        $shipment = Courier::book($order);
        $order->update(['courier_name' => $shipment->carrier, 'status' => 'out_for_delivery']);

        return response()->json(['data' => $this->detail($order)]);
    }

    /**
     * Admin override on a seller's package: correct carrier/tracking (no edit
     * limit for admin) or set its status (e.g. delivered, lost, returned).
     */
    public function updatePackage(Request $request, OrderPackage $package): JsonResponse
    {
        $data = $request->validate([
            'carrier' => ['sometimes', Rule::in(array_keys(Market::allCarriers()))],
            'tracking_number' => ['sometimes', 'string', 'min:6', 'max:60'],
            'status' => ['sometimes', Rule::in(['shipped', 'in_transit', 'delivered', 'returned', 'lost'])],
        ]);

        if (isset($data['tracking_number'])) {
            $data['tracking_number'] = strtoupper(preg_replace('/\s+/', '', $data['tracking_number']));
        }
        if (isset($data['status'])) {
            $data['delivered_at'] = $data['status'] === 'delivered' ? ($package->delivered_at ?? now()) : null;
        }
        $package->update($data);
        SellerFulfillment::sync($package->order);

        return response()->json(['data' => $this->detail($package->order)]);
    }

    /** The order shape shared by every action response here. */
    private function detail(Order $order): Order
    {
        $fresh = $order->fresh()->load([
            'items', 'user:id,name,email,phone', 'deliveryPartner:id,name', 'store:id,name,city', 'shipment',
            'packages.items', 'packages.shop:id,name', 'shopShipping.shop:id,name', 'labelRequests',
            'riderReview:id,order_id,rating,comment,source',
            'supportThreads:id,order_id,rating,rating_comment',
            'giftCards:id,order_id,code,initial_cents,balance_cents,reason,issued_by,created_at',
            'giftCards.issuedBy:id,name',
            'refunds.creator:id,name',
        ]);
        $this->attachCustomerNames([$fresh]);

        return $fresh;
    }

    /** @param  iterable<Order>  $orders */
    private function attachCustomerNames(iterable $orders): void
    {
        $names = CustomerNames::map();
        foreach ($orders as $order) {
            if ($order->user) {
                $order->user->display_name = $names[$order->user->id] ?? CustomerNames::base($order->user);
            }
        }
    }
}
