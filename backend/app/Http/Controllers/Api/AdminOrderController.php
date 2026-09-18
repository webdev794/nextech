<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use App\Notifications\RiderAssigned;
use App\Support\CustomerNames;
use App\Support\DeliveryOfferSweeper;
use App\Support\RiderAssignment;
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
            ->with([
                'items', 'user:id,name,email,phone', 'deliveryPartner:id,name', 'store:id,name,city',
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
            'items', 'user:id,name,email,phone', 'store:id,name,city',
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

        $order->update($changes);

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

        // An order that just became ready for delivery with no rider gets one
        // auto-assigned (nearest rider linked to its store); if none is eligible
        // it drops into the first-come pool as before.
        if (($changes['status'] ?? null) === 'ready_for_delivery' && ! $order->delivery_partner_id) {
            RiderAssignment::assign($order);
        }

        // Marking delivered, or collecting cash on a delivered order, sends the
        // customer their summary email with the PDF bill.
        $order->refresh()->sendDeliveredReceiptIfReady();

        $fresh = $order->fresh()->load([
            'items', 'user:id,name,email,phone', 'deliveryPartner:id,name', 'store:id,name,city',
            'riderReview:id,order_id,rating,comment,source',
            'supportThreads:id,order_id,rating,rating_comment',
            'giftCards:id,order_id,code,initial_cents,balance_cents,reason,issued_by,created_at',
            'giftCards.issuedBy:id,name',
            'refunds.creator:id,name',
        ]);
        $this->attachCustomerNames([$fresh]);

        return response()->json(['data' => $fresh]);
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
